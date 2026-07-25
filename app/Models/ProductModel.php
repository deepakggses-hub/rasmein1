<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Product;
use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table          = 'products';
    protected $primaryKey     = 'id';
    protected $returnType     = Product::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'category_id', 'sku', 'name', 'slug', 'short_description', 'description',
        'price', 'compare_at_price', 'stock_qty', 'low_stock_threshold',
        'track_inventory', 'weight_grams', 'unit_label', 'sale_mode',
        'is_giftbox_eligible', 'giftbox_slots', 'is_featured', 'is_active',
        'sort_order', 'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'sku'                 => 'required|max_length[60]|is_unique[products.sku,id,{id}]',
        'name'                => 'required|min_length[2]|max_length[191]',
        'slug'                => 'required|max_length[200]|regex_match[/^[a-z0-9-]+$/]|is_unique[products.slug,id,{id}]',
        'price'               => 'required|decimal|greater_than_equal_to[0]',
        'compare_at_price'    => 'permit_empty|decimal|greater_than_equal_to[0]',
        'stock_qty'           => 'permit_empty|is_integer|greater_than_equal_to[0]',
        'low_stock_threshold' => 'permit_empty|is_integer|greater_than_equal_to[0]',
        'category_id'         => 'permit_empty|is_natural_no_zero',
        'sale_mode'           => 'required|in_list[inherit,buy_now,enquire_now]',
        'giftbox_slots'       => 'required|is_natural_no_zero|less_than_equal_to[24]',
        'short_description'   => 'permit_empty|max_length[255]',
        'meta_title'          => 'permit_empty|max_length[191]',
        'meta_description'    => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'sku' => [
            'is_unique' => 'That SKU is already in use by another product.',
        ],
        'slug' => [
            'regex_match' => 'The URL slug may only contain lowercase letters, numbers and hyphens.',
            'is_unique'   => 'Another product already uses that URL slug.',
        ],
        'giftbox_slots' => [
            'less_than_equal_to' => 'A single product cannot fill more than 24 compartments.',
        ],
    ];

    /**
     * Adds the primary image path as `primary_image` without duplicating rows.
     * A correlated subquery is cheaper here than a join plus GROUP BY.
     */
    public function withPrimaryImage(): self
    {
        // escape = false: this is a hand-written expression, and CI4's
        // identifier protection would rewrite the keywords inside it.
        $this->select(
            'products.*, ('
            . 'SELECT pi.path FROM product_images pi'
            . ' WHERE pi.product_id = products.id'
            . ' ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC'
            . ' LIMIT 1'
            . ') AS primary_image',
            false
        );

        return $this;
    }

    /** Only rows a storefront visitor may see. */
    public function scopeVisible(): self
    {
        $this->where('products.is_active', 1);

        return $this;
    }

    /** @return list<Product> */
    public function featured(int $limit = 8): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.is_featured', 1)
            ->orderBy('products.sort_order', 'ASC')
            ->orderBy('products.id', 'DESC')
            ->findAll($limit);
    }

    /** @return list<Product> */
    public function latest(int $limit = 8): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->orderBy('products.id', 'DESC')
            ->findAll($limit);
    }

    /** @return list<Product> */
    public function giftBoxEligible(int $limit = 0): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.is_giftbox_eligible', 1)
            ->orderBy('products.sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findVisibleBySlug(string $slug): ?Product
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.slug', $slug)
            ->first();
    }

    /** @return list<Product> */
    public function inCategory(int $categoryId, int $limit = 0): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.category_id', $categoryId)
            ->orderBy('products.sort_order', 'ASC')
            ->findAll($limit);
    }

    /**
     * Decrement stock atomically. Returns false when the reduction would take
     * the product below zero, so two concurrent checkouts cannot oversell.
     */
    public function reserveStock(int $productId, int $quantity): bool
    {
        $sql = 'UPDATE products SET stock_qty = stock_qty - ?'
            . ' WHERE id = ? AND (track_inventory = 0 OR stock_qty >= ?)';

        $this->db->query($sql, [$quantity, $productId, $quantity]);

        return $this->db->affectedRows() === 1;
    }
}
