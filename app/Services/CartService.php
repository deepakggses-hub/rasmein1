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
            $couponCode ?? $cart['coupon_code'],
            $cart['customer_id'] !== null ? (int) $cart['customer_id'] : null,
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
    public function addProduct(int $productId, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));
        $product  = model(ProductModel::class)->find($productId);

        if ($product === null || ! $product->is_active) {
            return ['ok' => false, 'message' => 'That product is not available.'];
        }

        if (! $product->inStock()) {
            return ['ok' => false, 'message' => $product->name . ' is sold out.'];
        }

        $cart  = $this->currentOrCreate();
        $items = model(CartItemModel::class);

        $maxLines = (int) $this->settings->get('max_cart_items', 50);
        $existing = $items->findProductLine((int) $cart['id'], $productId);

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
        if ($product->track_inventory && $wanted > $product->stock_qty) {
            $wanted = $product->stock_qty;

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

            if ($product !== null && $product->track_inventory && $quantity > $product->stock_qty) {
                $quantity = max(1, $product->stock_qty);

                $items->update($lineId, ['quantity' => $quantity]);
                model(CartModel::class)->touch((int) $cart['id']);

                return [
                    'ok'      => false,
                    'message' => 'Only ' . $product->stock_qty . ' left — quantity adjusted.',
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
}
