<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lines on a cart. A line is either a single product or a built gift box.
 *
 * The *_snapshot columns are what the cart page renders so it does not need a
 * join per row. They are display values. PricingService recomputes every
 * figure from the products table before an order is written — see CLAUDE.md §8.
 */
class CartItemModel extends Model
{
    protected $table         = 'cart_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'cart_id', 'item_type', 'product_id', 'gift_box_id', 'quantity',
        'gift_recipient', 'gift_message', 'special_note',
        'unit_price_snapshot', 'line_total_snapshot', 'slots_used',
    ];

    protected $validationRules = [
        'cart_id'        => 'required|is_natural_no_zero',
        'item_type'      => 'required|in_list[product,gift_box]',
        'quantity'       => 'required|is_natural_no_zero|less_than_equal_to[99]',
        'gift_message'   => 'permit_empty|max_length[500]',
        'special_note'   => 'permit_empty|max_length[500]',
        'gift_recipient' => 'permit_empty|max_length[120]',
    ];

    protected $validationMessages = [
        'quantity' => [
            'less_than_equal_to' => 'Ninety-nine of one item is our limit — get in touch for bulk orders.',
        ],
    ];

    /**
     * Every line on a cart, with enough product detail to render the page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCart(int $cartId): array
    {
        return $this->select(
            'cart_items.*,'
            . ' products.name AS product_name, products.slug AS product_slug,'
            . ' products.sku AS product_sku, products.price AS product_price,'
            . ' products.stock_qty, products.track_inventory, products.is_active AS product_active,'
            . ' products.sale_mode AS product_sale_mode, products.unit_label,'
            . ' gift_boxes.name AS box_name, gift_boxes.slug AS box_slug,'
            . ' gift_boxes.base_price AS box_base_price, gift_boxes.capacity_slots,'
            . ' gift_boxes.sale_mode AS box_sale_mode, gift_boxes.is_active AS box_active,'
            . ' (SELECT pi.path FROM product_images pi WHERE pi.product_id = products.id'
            . '  ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS product_image',
            false
        )
            ->join('products', 'products.id = cart_items.product_id', 'left')
            ->join('gift_boxes', 'gift_boxes.id = cart_items.gift_box_id', 'left')
            ->where('cart_items.cart_id', $cartId)
            ->orderBy('cart_items.id', 'ASC')
            ->findAll();
    }

    /** An existing plain-product line, so adding the same thing increments it. */
    public function findProductLine(int $cartId, int $productId): ?array
    {
        return $this->where('cart_id', $cartId)
            ->where('item_type', 'product')
            ->where('product_id', $productId)
            ->first();
    }

    public function countForCart(int $cartId): int
    {
        return $this->where('cart_id', $cartId)->countAllResults();
    }
}
