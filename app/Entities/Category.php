<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Category extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'          => 'int',
        'parent_id'   => '?int',
        'sort_order'  => 'int',
        'is_featured' => 'bool',
        'is_active'   => 'bool',
    ];

    public function url(): string
    {
        return site_url('shop/' . $this->attributes['slug']);
    }

    public function imageUrl(): string
    {
        return rs_image($this->attributes['image'] ?? null, 'products');
    }

    /** Set by CategoryModel::withProductCounts(); null when not selected. */
    public function productCount(): ?int
    {
        return isset($this->attributes['product_count'])
            ? (int) $this->attributes['product_count']
            : null;
    }
}
