<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItemComponentModel;
use App\Models\CartItemModel;
use App\Models\CouponModel;
use App\Models\EnquiryModel;
use App\Models\OrderItemComponentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\ProductModel;
use Config\Rasmein;
use Throwable;

/**
 * Turns a cart into an order — the one operation in the application that must
 * either happen completely or not at all.
 *
 * Everything runs inside a single transaction: the order, its line snapshots,
 * the stock reservation, the coupon redemption and the enquiry record. If any
 * step fails the whole thing rolls back, so there is never an order with no
 * items or stock deducted for a sale that did not happen.
 *
 * Three protections worth naming:
 *
 *  1. The cart is RE-PRICED here from the database. Whatever total the checkout
 *     page displayed is ignored. If a price changed while the customer was
 *     filling the form, the order records the real one.
 *  2. Stock is taken with a conditional UPDATE, so two simultaneous checkouts
 *     cannot both claim the last jar.
 *  3. An idempotency key carried on the form makes a double-click, a refresh or
 *     a retried request return the SAME order instead of creating a second one.
 */
class OrderService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly PricingService $pricing,
        private readonly CartService $cart
    ) {
    }

    /**
     * @param array<string, mixed> $input Validated customer/address fields
     *
     * @return array{ok: bool, order: array<string, mixed>|null, error: string|null, duplicate?: bool}
     */
    public function placeFromCart(array $input, string $idempotencyKey): array
    {
        $orders = model(OrderModel::class);

        // ---------------------------------------------------- idempotency
        $existing = $orders->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return ['ok' => true, 'order' => $existing, 'error' => null, 'duplicate' => true];
        }

        // ------------------------------------------- re-price from source
        $snapshot = $this->cart->snapshot();

        if ($snapshot['cart'] === null || $snapshot['is_empty']) {
            return ['ok' => false, 'order' => null, 'error' => 'Your cart is empty.'];
        }

        if ($snapshot['blocking'] !== []) {
            return [
                'ok'    => false,
                'order' => null,
                'error' => $snapshot['blocking'][0]['message'],
            ];
        }

        $journey     = $snapshot['journey_mode'];
        $isEnquiry   = $journey === Rasmein::MODE_ENQUIRE;
        $brand       = config(Rasmein::class);
        $db          = db_connect();

        $db->transBegin();

        try {
            // ------------------------------------------------- the order
            $orderId = $orders->insert($this->buildOrderRow(
                $snapshot,
                $input,
                $journey,
                $idempotencyKey
            ), true);

            if ($orderId === false || $orderId === null) {
                throw new \RuntimeException('Order row could not be written.');
            }

            $orderId = (int) $orderId;

            // The public reference is derived from the row id, so it is gapless
            // and needs no separate counter table.
            $orders->update($orderId, [
                'order_ref' => $orders->buildRef($brand->orderRefPrefix, $orderId),
            ]);

            // ------------------------------------------- lines + snapshots
            $this->writeLines($orderId, $snapshot['lines']);

            // ------------------------------------------------------ stock
            // Only a purchase reserves stock. An enquiry is a request, not a
            // sale — holding inventory for it would starve real orders.
            if (! $isEnquiry) {
                $this->reserveStock($snapshot['lines']);
            }

            // ----------------------------------------------------- coupon
            if ($snapshot['coupon'] !== null) {
                $this->recordRedemption(
                    $snapshot,
                    $orderId,
                    (string) $input['customer_email']
                );
            }

            // ---------------------------------------------------- history
            model(OrderStatusHistoryModel::class)->record(
                $orderId,
                null,
                'pending',
                $isEnquiry ? 'Enquiry submitted by customer' : 'Order placed by customer'
            );

            // ---------------------------------------------------- enquiry
            if ($isEnquiry) {
                $this->createEnquiry($orderId, $input, (float) $snapshot['grand_total']);
            }

            // ------------------------------------------------------- cart
            $this->cart->markConverted((int) $snapshot['cart']['id'], $orderId);

            // ---------------------------------------------- notifications
            $this->queueNotifications($orderId, $input, $isEnquiry);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction reported failure.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            log_message('critical', 'Order could not be placed: {msg} @ {file}:{line}', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => (string) $e->getLine(),
            ]);

            return [
                'ok'    => false,
                'order' => null,
                'error' => 'We could not complete that just now. Nothing has been charged '
                    . 'and your cart is untouched — please try again.',
            ];
        }

        return ['ok' => true, 'order' => $orders->find($orderId), 'error' => null];
    }

    // =================================================================
    // Pieces
    // =================================================================

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function buildOrderRow(
        array $snapshot,
        array $input,
        string $journey,
        string $idempotencyKey
    ): array {
        $isEnquiry      = $journey === Rasmein::MODE_ENQUIRE;
        $paymentEnabled = (bool) $this->settings->get('payment_enabled', false);

        // With no gateway live yet, a purchase is recorded as placed and unpaid
        // rather than pretending money moved.
        $paymentStatus = $isEnquiry
            ? 'not_applicable'
            : ($paymentEnabled ? 'pending' : 'unpaid');

        $sameAsShip = ! empty($input['bill_same_as_ship']);
        $request    = service('request');

        return [
            // A temporary unique value; replaced with RSM-YYYY-NNNNNN once the
            // row has an id. order_ref is NOT NULL and unique, so it cannot be
            // left empty even for a moment.
            'order_ref'       => 'TMP-' . bin2hex(random_bytes(8)),
            'uuid'            => $this->uuid4(),
            'customer_id'     => $snapshot['cart']['customer_id'],
            'cart_id'         => $snapshot['cart']['id'],
            'journey_mode'    => $journey,
            'status'          => 'pending',
            'payment_status'  => $paymentStatus,
            'payment_method'  => null,
            'idempotency_key' => $idempotencyKey,

            'currency'       => $snapshot['currency'],
            'subtotal'       => $snapshot['subtotal'],
            'discount_total' => $snapshot['discount_total'],
            'shipping_total' => $snapshot['shipping_total'],
            'tax_total'      => $snapshot['tax_total'],
            'grand_total'    => $snapshot['grand_total'],
            'coupon_id'      => $snapshot['coupon']['id'] ?? null,
            'coupon_code'    => $snapshot['coupon']['code'] ?? null,

            'customer_name'  => $input['customer_name'],
            'customer_email' => $input['customer_email'],
            'customer_phone' => $input['customer_phone'],

            'ship_name'        => $input['ship_name'] ?? $input['customer_name'],
            'ship_phone'       => $input['ship_phone'] ?? $input['customer_phone'],
            'ship_line1'       => $input['ship_line1'] ?? null,
            'ship_line2'       => $input['ship_line2'] ?? null,
            'ship_landmark'    => $input['ship_landmark'] ?? null,
            'ship_city'        => $input['ship_city'] ?? null,
            'ship_state'       => $input['ship_state'] ?? null,
            'ship_postal_code' => $input['ship_postal_code'] ?? null,
            'ship_country'     => $input['ship_country'] ?? 'India',

            'bill_same_as_ship' => $sameAsShip ? 1 : 0,
            'bill_name'         => $sameAsShip ? null : ($input['bill_name'] ?? null),
            'bill_line1'        => $sameAsShip ? null : ($input['bill_line1'] ?? null),
            'bill_line2'        => $sameAsShip ? null : ($input['bill_line2'] ?? null),
            'bill_city'         => $sameAsShip ? null : ($input['bill_city'] ?? null),
            'bill_state'        => $sameAsShip ? null : ($input['bill_state'] ?? null),
            'bill_postal_code'  => $sameAsShip ? null : ($input['bill_postal_code'] ?? null),
            'bill_country'      => $sameAsShip ? null : ($input['bill_country'] ?? null),
            'bill_gstin'        => $input['bill_gstin'] ?? null,

            'gift_message'  => $input['gift_message'] ?? null,
            'customer_note' => $input['customer_note'] ?? null,

            'placed_at'  => date('Y-m-d H:i:s'),
            'ip_address' => $request->getIPAddress(),
            'user_agent' => rs_user_agent(),
        ];
    }

    /**
     * Copy the priced lines onto the order as permanent snapshots. These are
     * never updated afterwards — an invoice must stay correct after a product
     * is renamed, repriced or deleted.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function writeLines(int $orderId, array $lines): void
    {
        $items      = model(OrderItemModel::class);
        $components = model(OrderItemComponentModel::class);

        foreach ($lines as $line) {
            $itemId = $items->insert([
                'order_id'       => $orderId,
                'item_type'      => $line['type'],
                'product_id'     => $line['product_id'],
                'gift_box_id'    => $line['gift_box_id'],
                'name_snapshot'  => $line['name'],
                'sku_snapshot'   => $line['sku'] !== '' ? $line['sku'] : null,
                'unit_price'     => $line['unit_price'],
                'quantity'       => $line['quantity'],
                'line_total'     => $line['line_total'],
                'slots_used'     => $line['slots_used'],
                'gift_recipient' => $line['gift_recipient'] ?? null,
                'gift_message'   => $line['gift_message'] ?? null,
                'special_note'   => $line['special_note'] ?? null,
            ], true);

            if ($itemId === false || $itemId === null) {
                throw new \RuntimeException('Order line could not be written.');
            }

            foreach ($line['components'] as $component) {
                $components->insert([
                    'order_item_id' => (int) $itemId,
                    'product_id'    => $component['product_id'],
                    'name_snapshot' => $component['name'],
                    'sku_snapshot'  => $component['sku'] !== '' ? $component['sku'] : null,
                    'unit_price'    => $component['unit_price'],
                    'quantity'      => $component['quantity'],
                    'slots_used'    => $component['slots_used'],
                    'line_total'    => $component['line_total'],
                ]);
            }
        }
    }

    /**
     * Take stock with a conditional UPDATE. If the row is gone by the time we
     * get here, the whole transaction fails rather than overselling.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function reserveStock(array $lines): void
    {
        $products = model(ProductModel::class);

        foreach ($lines as $line) {
            if ($line['type'] === 'product' && $line['product_id'] !== null) {
                if (! $products->reserveStock((int) $line['product_id'], (int) $line['quantity'])) {
                    throw new \RuntimeException(
                        'Stock no longer available for product ' . $line['product_id']
                    );
                }

                continue;
            }

            foreach ($line['components'] as $component) {
                if ($component['product_id'] === null) {
                    continue;
                }

                $needed = (int) $component['quantity'] * (int) $line['quantity'];

                if (! $products->reserveStock((int) $component['product_id'], $needed)) {
                    throw new \RuntimeException(
                        'Stock no longer available for product ' . $component['product_id']
                    );
                }
            }
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function recordRedemption(array $snapshot, int $orderId, string $email): void
    {
        $coupon = $snapshot['coupon'];
        $db     = db_connect();

        $db->table('coupon_redemptions')->insert([
            'coupon_id'       => $coupon['id'],
            'order_id'        => $orderId,
            'customer_id'     => $snapshot['cart']['customer_id'],
            'email'           => $email,
            'discount_amount' => $snapshot['discount_total'],
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Increment in SQL rather than read-modify-write, so two concurrent
        // checkouts cannot both write the same count.
        $db->query('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$coupon['id']]);
    }

    /** @param array<string, mixed> $input */
    private function createEnquiry(int $orderId, array $input, float $estimated): void
    {
        $model = model(EnquiryModel::class);

        $enquiryId = $model->insert([
            'order_id'          => $orderId,
            'enquiry_ref'       => 'TMP-' . bin2hex(random_bytes(8)),
            'lead_status'       => 'new',
            'source'            => 'website',
            'company'           => $input['company'] ?? null,
            'preferred_contact' => $input['preferred_contact'] ?? 'phone',
            'requirement_note'  => $input['requirement_note'] ?? null,
            'expected_quantity' => isset($input['expected_quantity']) && $input['expected_quantity'] !== ''
                ? (int) $input['expected_quantity']
                : null,
            'needed_by'         => $input['needed_by'] ?? null,
            'estimated_value'   => $estimated,
            'spam_score'        => (int) ($input['spam_score'] ?? 0),
        ], true);

        if ($enquiryId === false || $enquiryId === null) {
            throw new \RuntimeException('Enquiry row could not be written.');
        }

        $model->update((int) $enquiryId, [
            'enquiry_ref' => $model->buildRef((int) $enquiryId),
        ]);
    }

    /**
     * Queue the outbound messages. Rows land as 'queued'; the sender is wired
     * in a later phase, and the log makes "did the customer get it?" answerable.
     *
     * @param array<string, mixed> $input
     */
    private function queueNotifications(int $orderId, array $input, bool $isEnquiry): void
    {
        $db    = db_connect();
        $now   = date('Y-m-d H:i:s');
        $event = $isEnquiry ? 'enquiry_received' : 'order_placed';

        $rows = [[
            'channel'      => 'email',
            'event'        => $event,
            'recipient'    => (string) $input['customer_email'],
            'subject'      => $isEnquiry
                ? 'We have your enquiry'
                : 'Your Rasmein order is confirmed',
            'template'     => $event . '_customer',
            'related_type' => 'order',
            'related_id'   => $orderId,
            'status'       => 'queued',
            'attempts'     => 0,
            'created_at'   => $now,
        ]];

        // Staff copy — enquiries in particular need a human to pick them up.
        $staff = $this->settings->get('enquiry_notify_emails', []);

        if (is_array($staff)) {
            foreach ($staff as $address) {
                $rows[] = [
                    'channel'      => 'email',
                    'event'        => $event,
                    'recipient'    => (string) $address,
                    'subject'      => $isEnquiry ? 'New enquiry received' : 'New order received',
                    'template'     => $event . '_staff',
                    'related_type' => 'order',
                    'related_id'   => $orderId,
                    'status'       => 'queued',
                    'attempts'     => 0,
                    'created_at'   => $now,
                ];
            }
        }

        $db->table('notification_log')->insertBatch($rows);
    }

    private function uuid4(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
