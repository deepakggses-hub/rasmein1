<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Orders — both journeys. `journey_mode` distinguishes a purchase from an
 * enquiry; see docs/DATABASE.md for why they share one table.
 */
class OrderModel extends Model
{
    protected $table          = 'orders';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'order_ref', 'uuid', 'customer_id', 'cart_id', 'journey_mode',
        'status', 'payment_status', 'payment_method', 'idempotency_key',
        'currency', 'subtotal', 'discount_total', 'shipping_total',
        'tax_total', 'grand_total', 'coupon_id', 'coupon_code',
        'customer_name', 'customer_email', 'customer_phone',
        'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_landmark',
        'ship_city', 'ship_state', 'ship_postal_code', 'ship_country',
        'bill_same_as_ship', 'bill_name', 'bill_line1', 'bill_line2',
        'bill_city', 'bill_state', 'bill_postal_code', 'bill_country', 'bill_gstin',
        'gift_message', 'customer_note', 'admin_note',
        'placed_at', 'confirmed_at', 'dispatched_at', 'delivered_at', 'cancelled_at',
        'ip_address', 'user_agent',
    ];

    /**
     * Validation for what the CUSTOMER supplies. Money columns are absent on
     * purpose — they are computed by PricingService, never posted.
     */
    protected $validationRules = [
        'customer_name'  => 'required|min_length[2]|max_length[120]',
        'customer_email' => 'required|valid_email|max_length[191]',
        'customer_phone' => 'required|min_length[10]|max_length[20]',
        'journey_mode'   => 'required|in_list[buy_now,enquire_now]',
        'gift_message'   => 'permit_empty|max_length[500]',
        'customer_note'  => 'permit_empty|max_length[1000]',
    ];

    public function findByUuid(string $uuid): ?array
    {
        return $this->where('uuid', $uuid)->first();
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        return $this->where('idempotency_key', $key)->first();
    }

    /**
     * Build the public reference from the row id, e.g. RSM-2026-000123.
     * Called after insert so the number is gapless and needs no counter table.
     */
    public function buildRef(string $prefix, int $id): string
    {
        return sprintf('%s-%s-%06d', $prefix, date('Y'), $id);
    }
}
