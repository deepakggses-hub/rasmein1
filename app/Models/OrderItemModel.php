<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Order lines. Every *_snapshot value is written once and never updated —
 * an invoice must stay correct after a product is renamed or repriced.
 */
class OrderItemModel extends Model
{
    protected $table         = 'order_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'order_id', 'item_type', 'product_id', 'gift_box_id',
        'name_snapshot', 'sku_snapshot', 'unit_price', 'quantity',
        'line_total', 'slots_used', 'gift_recipient', 'gift_message', 'special_note',
    ];

    protected $validationRules = [
        'order_id'      => 'required|is_natural_no_zero',
        'item_type'     => 'required|in_list[product,gift_box]',
        'name_snapshot' => 'required|max_length[191]',
        'quantity'      => 'required|is_natural_no_zero',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->where('order_id', $orderId)->orderBy('id', 'ASC')->findAll();
    }
}
