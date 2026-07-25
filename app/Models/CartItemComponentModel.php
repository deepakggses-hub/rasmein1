<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** The products inside a gift-box cart line. */
class CartItemComponentModel extends Model
{
    protected $table         = 'cart_item_components';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'cart_item_id', 'product_id', 'quantity', 'slots_used', 'unit_price_snapshot',
    ];

    protected $validationRules = [
        'cart_item_id' => 'required|is_natural_no_zero',
        'product_id'   => 'required|is_natural_no_zero',
        'quantity'     => 'required|is_natural_no_zero|less_than_equal_to[24]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forItem(int $cartItemId): array
    {
        return $this->select(
            'cart_item_components.*, products.name AS product_name,'
            . ' products.sku AS product_sku, products.price AS product_price,'
            . ' products.giftbox_slots, products.is_active AS product_active',
            false
        )
            ->join('products', 'products.id = cart_item_components.product_id', 'left')
            ->where('cart_item_id', $cartItemId)
            ->orderBy('cart_item_components.id', 'ASC')
            ->findAll();
    }

    /** @return array<int, array<string, mixed>> Components for many lines at once. */
    public function forItems(array $cartItemIds): array
    {
        if ($cartItemIds === []) {
            return [];
        }

        return $this->select(
            'cart_item_components.*, products.name AS product_name,'
            . ' products.sku AS product_sku, products.price AS product_price,'
            . ' products.giftbox_slots, products.is_active AS product_active',
            false
        )
            ->join('products', 'products.id = cart_item_components.product_id', 'left')
            ->whereIn('cart_item_id', $cartItemIds)
            ->orderBy('cart_item_components.id', 'ASC')
            ->findAll();
    }
}
