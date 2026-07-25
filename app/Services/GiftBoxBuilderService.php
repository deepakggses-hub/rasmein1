<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItemComponentModel;
use App\Models\CartItemModel;
use App\Models\CartModel;
use App\Models\CategoryModel;
use App\Models\GiftBoxModel;
use App\Models\ProductModel;

/**
 * The Build-Your-Own-Gift-Box flow.
 *
 * DESIGN NOTE — where the builder keeps its state.
 *
 * The box under construction IS a cart line (`cart_items` with
 * item_type = 'gift_box') and its contents ARE that line's
 * `cart_item_components`. There is no separate draft table and no client-side
 * basket.
 *
 * That choice buys four things at once:
 *   - the server owns the contents, so capacity and eligibility are enforced
 *     where it matters rather than in JavaScript;
 *   - a half-built box survives a refresh, a phone dying, or a return next day;
 *   - "edit this box" from the cart is not a feature, it is the same URL;
 *   - the box needs no conversion step when it is ordered.
 *
 * The cost is that an abandoned box sits in the cart looking unfinished. That is
 * handled honestly: PricingService flags any box below its minimum as blocking,
 * and the cart says "fill it to continue" rather than letting it reach checkout.
 */
class GiftBoxBuilderService
{
    public function __construct(
        private readonly CartService $cart
    ) {
    }

    // =================================================================
    // Starting and loading
    // =================================================================

    /**
     * Find this visitor's in-progress box for a given design, or start one.
     *
     * @return array{ok: bool, line_id: int|null, message: string}
     */
    public function startOrResume(string $boxSlug): array
    {
        $box = model(GiftBoxModel::class)->findActiveBySlug($boxSlug);

        if ($box === null) {
            return ['ok' => false, 'line_id' => null, 'message' => 'That box is not available.'];
        }

        $cart  = $this->cart->currentOrCreate();
        $items = model(CartItemModel::class);

        // Resume the most recent unfinished box of this design rather than
        // starting a second one — a refresh must not multiply boxes.
        $existing = $items
            ->where('cart_id', $cart['id'])
            ->where('item_type', 'gift_box')
            ->where('gift_box_id', $box->id)
            ->orderBy('id', 'DESC')
            ->first();

        if ($existing !== null) {
            return ['ok' => true, 'line_id' => (int) $existing['id'], 'message' => 'Picking up where you left off.'];
        }

        $lineId = $items->insert([
            'cart_id'             => $cart['id'],
            'item_type'           => 'gift_box',
            'gift_box_id'         => $box->id,
            'quantity'            => 1,
            'unit_price_snapshot' => $box->base_price,
            'line_total_snapshot' => $box->base_price,
            'slots_used'          => 0,
        ], true);

        if ($lineId === false || $lineId === null) {
            return ['ok' => false, 'line_id' => null, 'message' => 'The box could not be started.'];
        }

        model(CartModel::class)->touch((int) $cart['id']);

        return ['ok' => true, 'line_id' => (int) $lineId, 'message' => ''];
    }

    /**
     * Load a box line, scoped to this visitor's cart.
     *
     * Scoping is the access control: a guessed line id belonging to someone
     * else resolves to null rather than being editable (IDOR — CLAUDE.md §6).
     *
     * @return array<string, mixed>|null
     */
    public function line(int $lineId): ?array
    {
        $cart = $this->cart->current();

        if ($cart === null) {
            return null;
        }

        foreach (model(CartItemModel::class)->forCart((int) $cart['id']) as $line) {
            if ((int) $line['id'] === $lineId && $line['item_type'] === 'gift_box') {
                return $line;
            }
        }

        return null;
    }

    /**
     * Everything the builder page needs: the box, its contents, the slot maths,
     * and the products that may be chosen.
     *
     * @return array<string, mixed>|null
     */
    public function state(int $lineId): ?array
    {
        $line = $this->line($lineId);

        if ($line === null) {
            return null;
        }

        $boxId      = (int) $line['gift_box_id'];
        $box        = model(GiftBoxModel::class)->find($boxId);
        $components = model(CartItemComponentModel::class)->forItem($lineId);

        $slotsUsed = 0;
        $contents  = 0.0;
        $chosen    = [];

        foreach ($components as $component) {
            $quantity = max(1, (int) $component['quantity']);
            $slotCost = max(1, (int) ($component['giftbox_slots'] ?? 1));

            $slotsUsed += $slotCost * $quantity;
            $contents += (float) $component['product_price'] * $quantity;

            $chosen[(int) $component['product_id']] = $quantity;
        }

        $capacity = (int) $box->capacity_slots;
        $minimum  = (int) $box->min_slots;

        return [
            'line'        => $line,
            'line_id'     => $lineId,
            'box'         => $box,
            'components'  => $components,
            'chosen'      => $chosen,
            'slots_used'  => $slotsUsed,
            'slots_free'  => max(0, $capacity - $slotsUsed),
            'capacity'    => $capacity,
            'min_slots'   => $minimum,
            'is_complete' => $slotsUsed >= $minimum && $slotsUsed <= $capacity,
            'is_full'     => $slotsUsed >= $capacity,
            'contents_total' => round($contents, 2),
            'catalogue'   => $this->availableProducts($boxId),
        ];
    }

    /**
     * The products this box may contain, grouped by category for the picker.
     *
     * Built from GiftBoxModel::allowedProductIds() — the same method the
     * validator uses, so what is offered and what is accepted cannot drift.
     *
     * @return array<int, array{category: string, products: list<\App\Entities\Product>}>
     */
    public function availableProducts(int $boxId): array
    {
        $allowed = model(GiftBoxModel::class)->allowedProductIds($boxId);

        if ($allowed === []) {
            return [];
        }

        $products = model(ProductModel::class)
            ->withPrimaryImage()
            ->scopeVisible()
            ->whereIn('products.id', $allowed)
            ->orderBy('products.category_id', 'ASC')
            ->orderBy('products.sort_order', 'ASC')
            ->findAll();

        $categoryNames = [];

        foreach (model(CategoryModel::class)->findAll() as $category) {
            $categoryNames[(int) $category->id] = $category->name;
        }

        $grouped = [];

        foreach ($products as $product) {
            $key = $product->category_id ?? 0;
            $grouped[$key]['category'] ??= $categoryNames[$key] ?? 'Everything else';
            $grouped[$key]['products'][] = $product;
        }

        return array_values($grouped);
    }

    // =================================================================
    // Changing the contents
    // =================================================================

    /**
     * Put a product in the box, or increase how many of it are in there.
     *
     * Four things are re-checked here regardless of what the page offered:
     * the product exists and is active, it is allowed in THIS box, it is within
     * any per-box cap, and it fits in the remaining compartments.
     *
     * @return array{ok: bool, message: string}
     */
    public function addProduct(int $lineId, int $productId, int $quantity = 1): array
    {
        $state = $this->state($lineId);

        if ($state === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        $quantity = max(1, min(24, $quantity));
        $product  = model(ProductModel::class)->find($productId);

        if ($product === null || ! $product->is_active) {
            return ['ok' => false, 'message' => 'That item is not available.'];
        }

        if (! $product->inStock()) {
            return ['ok' => false, 'message' => $product->name . ' is sold out.'];
        }

        $boxId = (int) $state['box']->id;

        if (! in_array($productId, model(GiftBoxModel::class)->allowedProductIds($boxId), true)) {
            return [
                'ok'      => false,
                'message' => $product->name . ' cannot go in the ' . $state['box']->name . '.',
            ];
        }

        $slotCost = max(1, (int) $product->giftbox_slots);
        $already  = $state['chosen'][$productId] ?? 0;
        $wanted   = $already + $quantity;

        // Per-box cap, if the admin set one.
        $caps = model(GiftBoxModel::class)->productCaps($boxId);

        if (isset($caps[$productId]) && $wanted > $caps[$productId]) {
            return [
                'ok'      => false,
                'message' => 'Up to ' . $caps[$productId] . ' × ' . $product->name . ' per box.',
            ];
        }

        // Stock cap.
        if ($product->track_inventory && $wanted > $product->stock_qty) {
            return ['ok' => false, 'message' => 'That is all the ' . $product->name . ' we have.'];
        }

        // Capacity. The authoritative check — the UI's running total is a hint.
        $extraSlots = $slotCost * $quantity;

        if ($state['slots_used'] + $extraSlots > $state['capacity']) {
            $free = $state['slots_free'];

            return [
                'ok'      => false,
                'message' => $free === 0
                    ? 'The box is full. Remove something to swap it out.'
                    : $product->name . ' takes ' . $slotCost . ' compartment'
                        . ($slotCost === 1 ? '' : 's') . ' and only ' . $free . ' '
                        . ($free === 1 ? 'is' : 'are') . ' left.',
            ];
        }

        $components = model(CartItemComponentModel::class);
        $existing   = $components->where('cart_item_id', $lineId)->where('product_id', $productId)->first();

        $payload = [
            'quantity'            => $wanted,
            'slots_used'          => $slotCost * $wanted,
            'unit_price_snapshot' => $product->price,
        ];

        if ($existing !== null) {
            $components->update($existing['id'], $payload);
        } else {
            $components->insert(array_merge($payload, [
                'cart_item_id' => $lineId,
                'product_id'   => $productId,
            ]));
        }

        $this->refreshLine($lineId);

        return ['ok' => true, 'message' => $product->name . ' added.'];
    }

    /** @return array{ok: bool, message: string} */
    public function setProductQuantity(int $lineId, int $productId, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->removeProduct($lineId, $productId);
        }

        $state = $this->state($lineId);

        if ($state === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        $current = $state['chosen'][$productId] ?? 0;

        if ($current === 0) {
            return $this->addProduct($lineId, $productId, $quantity);
        }

        if ($quantity === $current) {
            return ['ok' => true, 'message' => ''];
        }

        if ($quantity < $current) {
            // Reducing always fits, so it needs no capacity check.
            $product  = model(ProductModel::class)->find($productId);
            $slotCost = $product !== null ? max(1, (int) $product->giftbox_slots) : 1;

            model(CartItemComponentModel::class)
                ->where('cart_item_id', $lineId)
                ->where('product_id', $productId)
                ->set(['quantity' => $quantity, 'slots_used' => $slotCost * $quantity])
                ->update();

            $this->refreshLine($lineId);

            return ['ok' => true, 'message' => 'Updated.'];
        }

        return $this->addProduct($lineId, $productId, $quantity - $current);
    }

    /** @return array{ok: bool, message: string} */
    public function removeProduct(int $lineId, int $productId): array
    {
        if ($this->line($lineId) === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        model(CartItemComponentModel::class)
            ->where('cart_item_id', $lineId)
            ->where('product_id', $productId)
            ->delete();

        $this->refreshLine($lineId);

        return ['ok' => true, 'message' => 'Removed from the box.'];
    }

    /** Empty the box without discarding it. @return array{ok: bool, message: string} */
    public function clear(int $lineId): array
    {
        if ($this->line($lineId) === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        model(CartItemComponentModel::class)->where('cart_item_id', $lineId)->delete();
        $this->refreshLine($lineId);

        return ['ok' => true, 'message' => 'Box emptied.'];
    }

    /** Discard the box entirely. @return array{ok: bool, message: string} */
    public function discard(int $lineId): array
    {
        if ($this->line($lineId) === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        return $this->cart->removeLine($lineId);
    }

    // =================================================================
    // Step 3 — personalise
    // =================================================================

    /**
     * Both fields are optional, as the brief requires. Lengths are capped
     * against the box's own limit, not just a global one.
     *
     * @return array{ok: bool, message: string}
     */
    public function personalise(
        int $lineId,
        ?string $recipient,
        ?string $message,
        ?string $note
    ): array {
        $state = $this->state($lineId);

        if ($state === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        $limit = max(1, (int) $state['box']->gift_message_max_chars);

        $clean = static fn (?string $value, int $max): ?string => $value === null || trim($value) === ''
            ? null
            : mb_substr(trim($value), 0, $max);

        // Honour the box's own switches: an admin can turn either field off
        // per design, and a posted value must not sneak past that.
        $allowsMessage = (bool) $state['box']->allow_gift_message;
        $allowsNote    = (bool) $state['box']->allow_special_note;

        model(CartItemModel::class)->update($lineId, [
            'gift_recipient' => $clean($recipient, 120),
            'gift_message'   => $allowsMessage ? $clean($message, $limit) : null,
            'special_note'   => $allowsNote ? $clean($note, 500) : null,
        ]);

        return ['ok' => true, 'message' => 'Saved.'];
    }

    /** Set how many identical copies of this box to order. */
    public function setQuantity(int $lineId, int $quantity): array
    {
        if ($this->line($lineId) === null) {
            return ['ok' => false, 'message' => 'That box is no longer in your cart.'];
        }

        return $this->cart->updateQuantity($lineId, $quantity);
    }

    // =================================================================
    // Internals
    // =================================================================

    /**
     * Keep the line's display columns in step with its contents.
     *
     * These are a convenience for the cart page only — PricingService always
     * recomputes from source, and a test asserts that tampering with them
     * changes nothing.
     */
    private function refreshLine(int $lineId): void
    {
        $line = $this->line($lineId);

        if ($line === null) {
            return;
        }

        $slots = 0;
        $total = (float) ($line['box_base_price'] ?? 0);

        foreach (model(CartItemComponentModel::class)->forItem($lineId) as $component) {
            $quantity = max(1, (int) $component['quantity']);
            $slots += max(1, (int) ($component['giftbox_slots'] ?? 1)) * $quantity;
            $total += (float) $component['product_price'] * $quantity;
        }

        model(CartItemModel::class)->update($lineId, [
            'slots_used'          => $slots,
            'unit_price_snapshot' => round($total, 2),
            'line_total_snapshot' => round($total * max(1, (int) $line['quantity']), 2),
        ]);

        $cart = $this->cart->current();

        if ($cart !== null) {
            model(CartModel::class)->touch((int) $cart['id']);
        }
    }
}
