<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class WishlistModel extends Model
{
    protected $table         = 'wishlist_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = ['customer_id', 'product_id'];

    protected $validationRules = [
        'customer_id' => 'required|is_natural_no_zero',
        'product_id'  => 'required|is_natural_no_zero',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forCustomer(int $customerId): array
    {
        return $this->select(
            'wishlist_items.*, products.name, products.slug, products.price,'
            . ' products.compare_at_price, products.stock_qty, products.track_inventory,'
            . ' products.is_active, products.unit_label, products.sale_mode,'
            . ' (SELECT pi.path FROM product_images pi WHERE pi.product_id = products.id'
            . '  ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image',
            false
        )
            ->join('products', 'products.id = wishlist_items.product_id')
            ->where('wishlist_items.customer_id', $customerId)
            ->where('products.deleted_at', null)
            ->orderBy('wishlist_items.id', 'DESC')
            ->findAll();
    }

    /** Add if absent, remove if present. Returns the resulting state. */
    public function toggle(int $customerId, int $productId): bool
    {
        $existing = $this->where('customer_id', $customerId)->where('product_id', $productId)->first();

        if ($existing !== null) {
            $this->delete($existing['id']);

            return false;
        }

        $this->insert(['customer_id' => $customerId, 'product_id' => $productId]);

        return true;
    }

    /** @return list<int> */
    public function productIds(int $customerId): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['product_id'],
            $this->select('product_id')->where('customer_id', $customerId)->findAll()
        );
    }
}
