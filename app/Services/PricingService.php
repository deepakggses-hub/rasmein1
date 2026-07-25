<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CouponModel;
use App\Models\GiftBoxPricingRuleModel;
use Config\Rasmein;

/**
 * The only place a total is decided.
 *
 * Everything here reads from the database. Nothing is taken from the cart's
 * *_snapshot columns, from a form field, or from a query string. The cart page
 * shows a running total for the customer's benefit; this class produces the
 * number actually charged — see CLAUDE.md §8.
 *
 * Amounts are rounded to 2dp per line and then summed, so the lines a customer
 * reads always add up to the total they are shown.
 */
class PricingService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    /**
     * Price a whole cart.
     *
     * @param array<int, array<string, mixed>> $lines      CartItemModel::forCart()
     * @param array<int, array<string, mixed>> $components CartItemComponentModel::forItems()
     *
     * @return array<string, mixed>
     */
    public function priceCart(
        array $lines,
        array $components = [],
        ?string $couponCode = null,
        ?int $customerId = null,
        ?string $email = null
    ): array {
        $brand    = config(Rasmein::class);
        $priced   = [];
        $subtotal = 0.0;
        $issues   = [];
        $slots    = 0;

        // Group components by their parent line.
        $byLine = [];

        foreach ($components as $component) {
            $byLine[(int) $component['cart_item_id']][] = $component;
        }

        foreach ($lines as $line) {
            $result = $line['item_type'] === 'gift_box'
                ? $this->priceGiftBoxLine($line, $byLine[(int) $line['id']] ?? [])
                : $this->priceProductLine($line);

            $subtotal += $result['line_total'];
            $slots += $result['slots_used'];
            $issues = array_merge($issues, $result['issues']);
            $priced[] = $result;
        }

        $subtotal = $this->round($subtotal);

        // ------------------------------------------------------------ coupon
        $coupon      = null;
        $couponError = null;
        $discount    = 0.0;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $check = $this->validateCoupon($couponCode, $subtotal, $customerId, $email);

            if ($check['ok']) {
                $coupon   = $check['coupon'];
                $discount = $check['amount'];
            } else {
                $couponError = $check['error'];
            }
        }

        $discount      = min($this->round($discount), $subtotal);
        $afterDiscount = $this->round($subtotal - $discount);

        // ---------------------------------------------------------- shipping
        $threshold = (float) $this->settings->get('free_shipping_threshold', 0);
        $flatRate  = (float) $this->settings->get('shipping_flat_rate', 0);
        $shipping  = 0.0;

        if ($subtotal > 0 && $flatRate > 0 && ($threshold <= 0 || $afterDiscount < $threshold)) {
            $shipping = $flatRate;
        }

        if ($coupon !== null && $coupon['discount_type'] === 'free_shipping') {
            $shipping = 0.0;
        }

        // --------------------------------------------------------------- tax
        $tax = 0.0;

        if ((bool) $this->settings->get('tax_enabled', false)) {
            $percent = (float) $this->settings->get('tax_percent', 0);
            $tax     = $this->round($afterDiscount * ($percent / 100));
        }

        $grand = $this->round($afterDiscount + $shipping + $tax);

        return [
            'lines'      => $priced,
            'item_count' => array_sum(array_map(static fn (array $l): int => $l['quantity'], $priced)),
            'line_count' => count($priced),
            'slots_used' => $slots,

            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'shipping_total' => $shipping,
            'tax_total'      => $tax,
            'grand_total'    => $grand,
            'currency'       => $brand->currency,

            'coupon'       => $coupon,
            'coupon_error' => $couponError,

            'issues'   => $issues,
            'blocking' => array_values(array_filter(
                $issues,
                static fn (array $i): bool => $i['severity'] === 'blocking'
            )),

            'journey_mode'  => $this->resolveJourney($priced),
            'free_shipping' => [
                'threshold' => $threshold,
                'remaining' => $threshold > 0 ? max(0.0, $this->round($threshold - $afterDiscount)) : 0.0,
                'earned'    => $threshold > 0 && $afterDiscount >= $threshold,
            ],
        ];
    }

    // =================================================================
    // Lines
    // =================================================================

    /**
     * @param array<string, mixed> $line
     *
     * @return array<string, mixed>
     */
    private function priceProductLine(array $line): array
    {
        $issues   = [];
        $quantity = max(1, (int) $line['quantity']);
        $name     = (string) ($line['product_name'] ?? 'Unavailable item');

        // Price comes from the products table, never from the cart row.
        $unit = (float) ($line['product_price'] ?? 0);

        $missing = ($line['product_id'] ?? null) === null
            || (int) ($line['product_active'] ?? 0) !== 1;

        if ($missing) {
            $issues[] = [
                'line_id'  => (int) $line['id'],
                'severity' => 'blocking',
                'message'  => $name . ' is no longer available. Remove it to continue.',
            ];
        }

        $available = $this->availableStock($line);

        if (! $missing && $available !== null && $quantity > $available) {
            $issues[] = [
                'line_id'  => (int) $line['id'],
                'severity' => $available === 0 ? 'blocking' : 'adjust',
                'message'  => $available === 0
                    ? $name . ' has sold out. Remove it to continue.'
                    : 'Only ' . $available . ' of ' . $name . ' left — reduce the quantity to continue.',
            ];
        }

        return [
            'line_id'      => (int) $line['id'],
            'type'         => 'product',
            'product_id'   => isset($line['product_id']) ? (int) $line['product_id'] : null,
            'gift_box_id'  => null,
            'name'         => $name,
            'sku'          => (string) ($line['product_sku'] ?? ''),
            'slug'         => $line['product_slug'] ?? null,
            'unit_label'   => $line['unit_label'] ?? null,
            'image'        => $line['product_image'] ?? null,
            'unit_price'   => $this->round($unit),
            'quantity'     => $quantity,
            'line_total'   => $this->round($unit * $quantity),
            'slots_used'   => 0,
            'capacity'     => null,
            'sale_mode'    => $this->settings->resolveItemMode($line['product_sale_mode'] ?? null),
            'gift_message' => $line['gift_message'] ?? null,
            'special_note' => $line['special_note'] ?? null,
            'components'   => [],
            'breakdown'    => [],
            'available'    => $available,
            'issues'       => $issues,
        ];
    }

    /**
     * Price a built gift box: the box charge plus its contents, with the box's
     * pricing rules applied. Capacity is re-checked here, not only in the
     * builder UI — CLAUDE.md §8.
     *
     * @param array<string, mixed>             $line
     * @param array<int, array<string, mixed>> $components
     *
     * @return array<string, mixed>
     */
    private function priceGiftBoxLine(array $line, array $components): array
    {
        $issues   = [];
        $quantity = max(1, (int) $line['quantity']);
        $name     = (string) ($line['box_name'] ?? 'Unavailable box');
        $capacity = (int) ($line['capacity_slots'] ?? 0);

        if (($line['gift_box_id'] ?? null) === null || (int) ($line['box_active'] ?? 0) !== 1) {
            $issues[] = [
                'line_id'  => (int) $line['id'],
                'severity' => 'blocking',
                'message'  => $name . ' is no longer available. Remove it to continue.',
            ];
        }

        // ------------------------------------------------------- contents
        $contents      = [];
        $contentsTotal = 0.0;
        $slotsUsed     = 0;

        foreach ($components as $component) {
            $componentQty  = max(1, (int) $component['quantity']);
            $componentUnit = (float) ($component['product_price'] ?? 0);
            $slotCost      = max(1, (int) ($component['giftbox_slots'] ?? 1));
            $componentName = (string) ($component['product_name'] ?? 'Unavailable item');

            if ((int) ($component['product_active'] ?? 0) !== 1) {
                $issues[] = [
                    'line_id'  => (int) $line['id'],
                    'severity' => 'blocking',
                    'message'  => $componentName . ' (inside ' . $name . ') is no longer available.',
                ];
            }

            $componentTotal = $this->round($componentUnit * $componentQty);
            $contentsTotal += $componentTotal;
            $slotsUsed += $slotCost * $componentQty;

            $contents[] = [
                'component_id' => (int) $component['id'],
                'product_id'   => isset($component['product_id']) ? (int) $component['product_id'] : null,
                'name'         => $componentName,
                'sku'          => (string) ($component['product_sku'] ?? ''),
                'unit_price'   => $this->round($componentUnit),
                'quantity'     => $componentQty,
                'slots_used'   => $slotCost * $componentQty,
                'line_total'   => $componentTotal,
            ];
        }

        $contentsTotal = $this->round($contentsTotal);

        if ($capacity > 0 && $slotsUsed > $capacity) {
            $issues[] = [
                'line_id'  => (int) $line['id'],
                'severity' => 'blocking',
                'message'  => $name . ' holds ' . $capacity . ' compartments but is filled with '
                    . $slotsUsed . '. Edit the box to continue.',
            ];
        }

        // -------------------------------------------------- pricing rules
        $boxCharge = (float) ($line['box_base_price'] ?? 0);
        $breakdown = [];
        $adjust    = 0.0;

        if (isset($line['gift_box_id'])) {
            $rules = model(GiftBoxPricingRuleModel::class)->activeForBox((int) $line['gift_box_id']);

            foreach ($rules as $rule) {
                $value = (float) $rule['value'];
                $min   = $rule['min_slots'] !== null ? (int) $rule['min_slots'] : null;
                $max   = $rule['max_slots'] !== null ? (int) $rule['max_slots'] : null;

                if ($min !== null && $slotsUsed < $min) {
                    continue;
                }

                if ($max !== null && $slotsUsed > $max) {
                    continue;
                }

                if ($rule['min_subtotal'] !== null && $contentsTotal < (float) $rule['min_subtotal']) {
                    continue;
                }

                switch ($rule['rule_type']) {
                    case 'flat_box_price':
                        $boxCharge = $value;
                        break;

                    case 'waive_box_price':
                        $boxCharge = 0.0;
                        break;

                    case 'percent_markup':
                        $adjust += $this->round($contentsTotal * ($value / 100));
                        break;

                    case 'slot_discount_percent':
                        $adjust -= $this->round($contentsTotal * ($value / 100));
                        break;

                    case 'slot_discount_amount':
                        $adjust -= $value;
                        break;
                }

                if ($rule['label'] !== null && $rule['label'] !== '') {
                    $breakdown[] = ['label' => (string) $rule['label'], 'rule' => $rule['rule_type']];
                }
            }
        }

        // One box can never price below zero, however the rules stack.
        $perBox    = max(0.0, $this->round($contentsTotal + $boxCharge + $adjust));
        $lineTotal = $this->round($perBox * $quantity);

        return [
            'line_id'        => (int) $line['id'],
            'type'           => 'gift_box',
            'product_id'     => null,
            'gift_box_id'    => isset($line['gift_box_id']) ? (int) $line['gift_box_id'] : null,
            'name'           => $name,
            'sku'            => '',
            'slug'           => $line['box_slug'] ?? null,
            'unit_label'     => $capacity > 0 ? $capacity . ' compartments' : null,
            'image'          => null,
            'unit_price'     => $perBox,
            'quantity'       => $quantity,
            'line_total'     => $lineTotal,
            'slots_used'     => $slotsUsed,
            'capacity'       => $capacity,
            'contents_total' => $contentsTotal,
            'box_charge'     => $this->round($boxCharge),
            'adjustment'     => $this->round($adjust),
            'sale_mode'      => $this->settings->resolveItemMode($line['box_sale_mode'] ?? null),
            'gift_message'   => $line['gift_message'] ?? null,
            'special_note'   => $line['special_note'] ?? null,
            'components'     => $contents,
            'breakdown'      => $breakdown,
            'available'      => null,
            'issues'         => $issues,
        ];
    }

    // =================================================================
    // Coupons
    // =================================================================

    /**
     * Validate a code and compute its value from the database.
     *
     * A discount amount is never accepted from the client — only the code is,
     * and every window, limit and threshold is re-checked here at the moment of
     * use (CLAUDE.md §8).
     *
     * @return array{ok: bool, coupon: array<string, mixed>|null, amount: float, error: string|null}
     */
    public function validateCoupon(
        string $code,
        float $subtotal,
        ?int $customerId = null,
        ?string $email = null
    ): array {
        $fail = static fn (string $message): array => [
            'ok'     => false,
            'coupon' => null,
            'amount' => 0.0,
            'error'  => $message,
        ];

        if (! (bool) $this->settings->get('coupons_enabled', true)) {
            return $fail('Coupon codes are not being accepted at the moment.');
        }

        $model  = model(CouponModel::class);
        $coupon = $model->findByCode($code);

        // One message covers "no such code" and "not active", so the form
        // cannot be used to work out which codes exist.
        if ($coupon === null || (int) $coupon['is_active'] !== 1) {
            return $fail('That code is not valid.');
        }

        $now = time();

        if ($coupon['starts_at'] !== null && strtotime((string) $coupon['starts_at']) > $now) {
            return $fail('That code is not active yet.');
        }

        if ($coupon['ends_at'] !== null && strtotime((string) $coupon['ends_at']) < $now) {
            return $fail('That code has expired.');
        }

        if ($coupon['usage_limit_total'] !== null
            && (int) $coupon['used_count'] >= (int) $coupon['usage_limit_total']) {
            return $fail('That code has been fully redeemed.');
        }

        $minimum = (float) $coupon['min_order_value'];

        if ($minimum > 0 && $subtotal < $minimum) {
            return $fail('That code needs a subtotal of at least ' . rs_money($minimum) . '.');
        }

        if ($coupon['usage_limit_per_customer'] !== null && ($customerId !== null || $email !== null)) {
            $used = $model->redemptionCount((int) $coupon['id'], $customerId, $email);

            if ($used >= (int) $coupon['usage_limit_per_customer']) {
                return $fail('You have already used that code.');
            }
        }

        // ----------------------------------------------------------- amount
        $amount = match ($coupon['discount_type']) {
            'percent'       => $this->round($subtotal * ((float) $coupon['value'] / 100)),
            'fixed'         => (float) $coupon['value'],
            'free_shipping' => 0.0,
            default         => 0.0,
        };

        if ($coupon['max_discount'] !== null && (float) $coupon['max_discount'] > 0) {
            $amount = min($amount, (float) $coupon['max_discount']);
        }

        // Never discount more than the basket is worth.
        $amount = min($this->round($amount), $subtotal);

        return ['ok' => true, 'coupon' => $coupon, 'amount' => $amount, 'error' => null];
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Which journey applies to the whole basket.
     *
     * If ANY line must be quoted, the entire order becomes an enquiry: we
     * cannot take payment for a basket containing something that has no price
     * yet, and splitting one basket into two orders would be worse for both the
     * customer and the fulfilment team.
     *
     * @param array<int, array<string, mixed>> $priced
     */
    public function resolveJourney(array $priced): string
    {
        if ($this->settings->isEnquireMode()) {
            return Rasmein::MODE_ENQUIRE;
        }

        foreach ($priced as $line) {
            if (($line['sale_mode'] ?? null) === Rasmein::MODE_ENQUIRE) {
                return Rasmein::MODE_ENQUIRE;
            }
        }

        return Rasmein::MODE_BUY;
    }

    /** Null means the product is not stock-tracked, so quantity is unlimited. */
    private function availableStock(array $line): ?int
    {
        if ((int) ($line['track_inventory'] ?? 0) !== 1) {
            return null;
        }

        return max(0, (int) ($line['stock_qty'] ?? 0));
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
