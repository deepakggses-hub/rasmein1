<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OrderItemComponentModel extends Model
{
    protected $table         = 'order_item_components';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'order_item_id', 'product_id', 'name_snapshot', 'sku_snapshot',
        'unit_price', 'quantity', 'slots_used', 'line_total',
    ];

    protected $validationRules = [
        'order_item_id' => 'required|is_natural_no_zero',
        'name_snapshot' => 'required|max_length[191]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forItems(array $orderItemIds): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        return $this->whereIn('order_item_id', $orderItemIds)->orderBy('id', 'ASC')->findAll();
    }
}
