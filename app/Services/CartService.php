<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItemComponentModel;
use App\Models\CartItemModel;
use App\Models\CartModel;
use App\Models\ProductModel;
use Config\Rasmein;

/**
 * Owns the cart lifecycle: find or create it, add and change lines, apply a
 * coupon code, and hand the priced result to whoever is rendering.
 *
 * The cart lives in the database, keyed by a UUID held in the session. That
 * means a guest basket survives a browser restart, an abandoned basket is
 * reportable, and — the important part — the server owns the line items, so
 * nothing about price or quantity depends on what the browser sends back.
 */
class CartService
{
    private const SESSION_KEY = 'cart_uuid';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PricingService $pricing
    ) {
    }

    // =================================================================
    // Lifecycle
    // =================================================================

    /**
     * The current cart, or null if this visitor has never had one.
     * Read-only callers use this so a bare page view does not create rows.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $session = session();
        $model   = model(CartModel::class);

        $customerId = $session->get('customer_id');

        if ($customerId !== null) {
            $cart = $model->findActiveForCustomer((int) $customerId);

            if ($cart !== null) {
                $session->set(self::SESSION_KEY, $cart['uuid']);

                return $cart;
            }
        }

        $uuid = $session->get(self::SESSION_KEY);

        if ($uuid === null) {
            return null;
        }

        $cart = $model->findActiveByUuid((string) $uuid);

        if ($cart === null) {
            $session->remove(self::SESSION_KEY);

            return null;
        }

        return $cart;
    }

    /**
     * The current cart, creating one if needed. Used by write operations only.
     *
     * @return array<string, mixed>
     */
    public function currentOrCreate(): array
    {
        $existing = $this->current();

        if ($existing !== null) {
            return $existing;
        }

        $session = session();
        $model   = model(CartModel::class);
        $uuid    = $this->uuid4();

        $id = $model->insert([
            'uuid'             => $uuid,
            'customer_id'      => $session->get('customer_id'),
            'session_id'       => $session->session_id ?? null,
            'status'           => 'active',
            'currency'         => config(Rasmein::class)->currency,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ], true);

        $session->set(self::SESSION_KEY, $uuid);

        return $model->find($id);
    }

    /**
     * Attach a guest cart to an account at sign-in.
     *
     * If the account already has a cart, the guest lines are moved onto it and
     * the guest cart is retired — a shopper who filled a basket before signing
     * in should not lose it.
     */
    public function attachToCustomer(int $customerId): void
    {
        $model = model(CartModel::class);
        $guest = $this->current();

        if ($guest === null) {
            return;
        }

        if ($guest['customer_id'] !== null && (int) $guest['customer_id'] === $customerId) {
            return;
        }

        $existing = $model->findActiveForCustomer($customerId);

        if ($existing === null) {
            $model->update($guest['id'], ['customer_id' => $customerId]);

            return;
        }

        // Move the guest's lines across, then retire the guest cart.
        model(CartItemModel::class)
            ->where('cart_id', $guest['id'])
            ->set('cart_id', $existing['id'])
            ->update();

        $model->update($guest['id'], ['status' => 'abandoned']);
        session()->set(self::SESSION_KEY, $existing['uuid']);
    }

    // =================================================================
    // Reading
    // =================================================================

    /**
     * The cart, fully priced. Safe to call when no cart exists — returns an
     * empty, zeroed result rather than null, so views need no special case.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?string $couponCode = null): array
    {
        $cart = $this->current();

        if ($cart === null) {
            return array_merge(
                $this->pricing->priceCart([], [], null),
                ['cart' => null, 'is_empty' => true]
            );
        }

        $lines      = model(CartItemModel::class)->forCart((int) $cart['id']);
        $lineIds    = array_map(static fn (array $l): int => (int) $l['id'], $lines);
        $components = model(CartItemComponentModel::class)->forItems($lineIds);

        $priced = $this->pricing->priceCart(
            $lines,
            $components,
            // ?? null so a database that has not had migration 011 applied
            // degrades to "no coupon" instead of throwing. Reported in the field.
            $couponCode ?? ($cart['coupon_code'] ?? null),
            isset($cart['customer_id']) && $cart['customer_id'] !== null ? (int) $cart['customer_id'] : null,
            session('customer_email')
        );

        return array_merge($priced, [
            'cart'     => $cart,
            'is_empty' => $lines === [],
        ]);
    }

    /** Line count for the header badge. Cheap: no pricing pass. */
    public function itemCount(): int
    {
        $cart = $this->current();

        if ($cart === null) {
            return 0;
        }

        return model(CartItemModel::class)->countForCart((int) $cart['id']);
    }

    // =================================================================
    // Writing
    // =================================================================

    /**
     * Add a product, or increase its quantity if already present.
     *
     * @return array{ok: bool, message: string, quantity?: int}
     */
    /**
     * What the customer chose, as a readable line.
     *
     * Stored as TEXT on the cart line, not as ids. An order is a record of what
     * was agreed — if a value is renamed or deleted a year later, "Colour:
     * Silver" must still read the way it did when it was placed.
     *
     * Only attributes actually marked selectable are kept, and only values that
     * really belong to the product, so a hand-edited form cannot write anything
     * it likes onto an order.
     */
    public function describeChoices(int $productId, array $posted): ?string
    {
        if ($posted === []) {
            return null;
        }

        $rows = model(\App\Models\AttributeValueModel::class)->grouped($productId);
        $out  = [];

        foreach ($rows as $code => $group) {
            if (! $group['selectable']) {
                continue;
            }

            $chosen = trim((string) ($posted[$code] ?? ''));

            if ($chosen === '') {
                continue;
            }

            $allowed = array_column($group['values'], 'label');

            if (in_array($chosen, $allowed, true)) {
                $out[] = $group['name'] . ': ' . $chosen;
            }
        }

        return $out === [] ? null : mb_substr(implode(' · ', $out), 0, 255);
    }

    /**
     * What the customer chose, set just before addProduct().
     *
     * A property rather than another argument: addProduct() is called from the
     * gift-box builder and the quick-add on cards too, and neither has a choice
     * to pass — widening the signature would make every caller say null.
     */
    public ?string $pendingChoices = null;

    /** The variant chosen on the product page, set just before addProduct(). */
    public ?int $pendingVariant = null;

    public function addProduct(int $productId, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));
        $product  = model(ProductModel::class)->find($productId);

        if ($product === null || ! $product->is_active) {
            return ['ok' => false, 'message' => 'That product is not available.'];
        }

        /*
         * The variant's stock is the real limit.
         *
         * Clamping against the product let a variant with 12 in stock take 25
         * into the basket; PricingService then refused at checkout and the
         * customer was told "only 12 left" AFTER the cart had accepted 25. The
         * ceiling belongs where the quantity is set, not where it is checked.
         */
        $variantStock = null;

        if ($this->pendingVariant !== null) {
            $row = db_connect()->table('product_variants')
                ->select('stock_qty')->where('id', $this->pendingVariant)->get()->getRowArray();

            if ($row !== null) {
                $variantStock = (int) $row['stock_qty'];
            }
        }

        if ($variantStock !== null && $variantStock < 1) {
            return ['ok' => false, 'message' => $product->name . ' is sold out in that option.'];
        }

        if ($variantStock === null && ! $product->inStock()) {
            return ['ok' => false, 'message' => $product->name . ' is sold out.'];
        }

        $cart  = $this->currentOrCreate();
        $items = model(CartItemModel::class);

        $maxLines = (int) $this->settings->get('max_cart_items', 50);
        $existing = $items->findProductLine((int) $cart['id'], $productId, $this->pendingVariant);

        if ($existing === null && $items->countForCart((int) $cart['id']) >= $maxLines) {
            return [
                'ok'      => false,
                'message' => 'Your ' . rs_cta_label(null, 'cart') . ' is full at ' . $maxLines
                    . ' lines. Check out, or get in touch for a bulk order.',
            ];
        }

        $wanted = $existing !== null
            ? (int) $existing['quantity'] + $quantity
            : $quantity;

        // Never let the cart hold more than exists.
        // The variant's ceiling wins when there is one.
        $ceiling = $variantStock ?? ($product->track_inventory ? (int) $product->stock_qty : null);

        if ($ceiling !== null && $wanted > $ceiling) {
            $wanted = $ceiling;

            if ($wanted <= (int) ($existing['quantity'] ?? 0)) {
                return [
                    'ok'      => false,
                    'message' => 'That is all the ' . $product->name . ' we have.',
                ];
            }
        }

        $wanted = min(99, $wanted);

        $payload = [
            'unit_price_snapshot' => $product->price,
            'line_total_snapshot' => round($product->price * $wanted, 2),
            'quantity'            => $wanted,
        ];

        if ($existing !== null) {
            $items->update($existing['id'], $payload);
        } else {
            $items->insert(array_merge($payload, [
                'cart_id'    => $cart['id'],
                'item_type'  => 'product',
                'product_id' => $productId,
                'slots_used' => 0,
                // Set by the controller just before this call, when the
                // customer picked a colour on the product page.
                'chosen_attributes' => $this->pendingChoices,
                'variant_id'        => $this->pendingVariant,
            ]));
        }

        model(CartModel::class)->touch((int) $cart['id']);

        return [
            'ok'       => true,
            'message'  => $product->name . ' added.',
            'quantity' => $wanted,
        ];
    }

    /**
     * Set an exact quantity on a line. Zero removes it.
     *
     * @return array{ok: bool, message: string}
     */
    public function updateQuantity(int $lineId, int $quantity): array
    {
        $cart = $this->current();

        if ($cart === null) {
            return ['ok' => false, 'message' => 'Your cart has expired.'];
        }

        $items = model(CartItemModel::class);
        $line  = $items->where('id', $lineId)->where('cart_id', $cart['id'])->first();

        // Scoped to this cart, so a guessed id cannot touch someone else's line.
        if ($line === null) {
            return ['ok' => false, 'message' => 'That item is not in your cart.'];
        }

        if ($quantity <= 0) {
            return $this->removeLine($lineId);
        }

        $quantity = min(99, $quantity);

        if ($line['item_type'] === 'product' && $line['product_id'] !== null) {
            $product = model(ProductModel::class)->find((int) $line['product_id']);

            // The line's own variant sets the ceiling, exactly as it does when
            // the line is first added.
            $ceiling = null;

            if (($line['variant_id'] ?? null) !== null) {
                $row = db_connect()->table('product_variants')
                    ->select('stock_qty')->where('id', (int) $line['variant_id'])->get()->getRowArray();

                if ($row !== null) {
                    $ceiling = (int) $row['stock_qty'];
                }
            }

            if ($ceiling === null && $product !== null && $product->track_inventory) {
                $ceiling = (int) $product->stock_qty;
            }

            if ($ceiling !== null && $quantity > $ceiling) {
                $quantity = max(1, $ceiling);

                $items->update($lineId, ['quantity' => $quantity]);
                model(CartModel::class)->touch((int) $cart['id']);

                return [
                    'ok'      => false,
                    'message' => 'Only ' . $ceiling . ' left — quantity adjusted.',
                ];
            }
        }

        $items->update($lineId, ['quantity' => $quantity]);
        model(CartModel::class)->touch((int) $cart['id']);

        return ['ok' => true, 'message' => 'Quantity updated.'];
    }

    /** @return array{ok: bool, message: string} */
    public function removeLine(int $lineId): array
    {
        $cart = $this->current();

        if ($cart === null) {
            return ['ok' => false, 'message' => 'Your cart has expired.'];
        }

        $items = model(CartItemModel::class);
        $line  = $items->where('id', $lineId)->where('cart_id', $cart['id'])->first();

        if ($line === null) {
            return ['ok' => false, 'message' => 'That item is not in your cart.'];
        }

        $items->delete($lineId);
        model(CartModel::class)->touch((int) $cart['id']);

        return ['ok' => true, 'message' => 'Removed.'];
    }

    /**
     * Store a coupon CODE on the cart. The value is never stored — it is
     * recomputed at every render and again at checkout.
     *
     * @return array{ok: bool, message: string}
     */
    public function applyCoupon(string $code): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter a code first.'];
        }

        $snapshot = $this->snapshot();

        if ($snapshot['is_empty']) {
            return ['ok' => false, 'message' => 'Add something to your cart first.'];
        }

        $check = $this->pricing->validateCoupon(
            $code,
            (float) $snapshot['subtotal'],
            $snapshot['cart']['customer_id'] !== null ? (int) $snapshot['cart']['customer_id'] : null,
            session('customer_email')
        );

        if (! $check['ok']) {
            return ['ok' => false, 'message' => (string) $check['error']];
        }

        model(CartModel::class)->update($snapshot['cart']['id'], ['coupon_code' => $code]);

        return [
            'ok'      => true,
            'message' => $check['amount'] > 0
                ? $code . ' applied — ' . rs_money($check['amount']) . ' off.'
                : $code . ' applied.',
        ];
    }

    public function removeCoupon(): void
    {
        $cart = $this->current();

        if ($cart !== null) {
            model(CartModel::class)->update($cart['id'], ['coupon_code' => null]);
        }
    }

    /** Called after an order is written, so the next visit starts clean. */
    public function markConverted(int $cartId, int $orderId): void
    {
        model(CartModel::class)->update($cartId, [
            'status'             => 'converted',
            'converted_order_id' => $orderId,
        ]);

        session()->remove(self::SESSION_KEY);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** RFC 4122 version 4 UUID from a CSPRNG. */
    private function uuid4(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Set the quantity of a loose product to an absolute number.
     *
     * The card stepper thinks in products, not cart lines — it has no idea a
     * line id exists. This finds the plain (non-gift-box) line for a product and
     * sets it, adding or removing as needed.
     *
     * Absolute, not a delta: a delta sent twice because a tap was slow gives two
     * increments, and the customer gets three of something they wanted one of.
     *
     * @return array<string, mixed>
     */
    /**
     * Set a product's quantity to an ABSOLUTE number.
     *
     * `$variantId` scopes it. Without that, choosing Gold when Silver was
     * already in the basket found the Silver line and incremented THAT — the
     * customer received a colour they never picked. The old query also had no
     * `orderBy`, so with two lines it took whichever the database handed back
     * first.
     */
    public function setProductQuantity(int $productId, int $quantity, ?int $variantId = null): array
    {
        $cart = $this->currentOrCreate();

        $builder = db_connect()->table('cart_items')
            ->where('cart_id', $cart['id'])
            ->where('product_id', $productId)
            // A configured gift box is a different line from a loose product of
            // the same id, and the stepper must never touch one.
            ->where('gift_box_id', null);

        $variantId === null
            ? $builder->where('variant_id', null)
            : $builder->where('variant_id', $variantId);

        // Deterministic: the oldest matching line, never an arbitrary one.
        $line = $builder->orderBy('id', 'ASC')->get()->getRowArray();

        if ($quantity < 1) {
            return $line === null
                ? ['ok' => true, 'error' => null]
                : $this->removeLine((int) $line['id']);
        }

        if ($line === null) {
            $this->pendingVariant = $variantId;

            return $this->addProduct($productId, $quantity);
        }

        return $this->updateQuantity((int) $line['id'], $quantity);
    }

    /** How many of a product are in the basket right now. */
    /**
     * How many of a product — and VARIANT — are in the basket.
     *
     * Scoped like setProductQuantity(). Without it, Silver x2 and Gold x1 in the
     * basket returned whichever row MySQL handed back first: the stepper on the
     * Gold card showed 2, and the next "+" then sent an absolute quantity
     * computed from the Silver line.
     */
    public function quantityOf(int $productId, ?int $variantId = null): int
    {
        $cart = $this->current();

        if ($cart === null) {
            return 0;
        }

        $builder = db_connect()->table('cart_items')
            ->where('cart_id', $cart['id'])
            ->where('product_id', $productId)
            ->where('gift_box_id', null);

        $variantId === null
            ? $builder->where('variant_id', null)
            : $builder->where('variant_id', $variantId);

        $row = $builder->orderBy('id', 'ASC')->get()->getRowArray();

        return $row === null ? 0 : (int) $row['quantity'];
    }

    /**
     * Quantities for a page of products, in one query.
     *
     * @param list<int> $productIds
     *
     * @return array<int, int>
     */
    public function quantitiesFor(array $productIds): array
    {
        $cart = $this->current();
        $ids  = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($cart === null || $ids === []) {
            return [];
        }

        $out = [];

        foreach (db_connect()->table('cart_items')
            ->select('product_id, quantity')
            ->where('cart_id', $cart['id'])
            ->where('gift_box_id', null)
            ->whereIn('product_id', $ids)
            ->get()->getResultArray() as $row) {
            $out[(int) $row['product_id']] = (int) $row['quantity'];
        }

        return $out;
    }
}
