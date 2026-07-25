<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Config\Rasmein;

/**
 * A catalogue product.
 *
 * Holds the derived logic that would otherwise be duplicated across every
 * view: stock state, discount maths, and which journey (Buy or Enquire) this
 * particular product follows.
 */
class Product extends Entity
{
    protected $datamap = [];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'                  => 'int',
        'category_id'         => '?int',
        'price'               => 'float',
        'compare_at_price'    => '?float',
        'stock_qty'           => 'int',
        'low_stock_threshold' => 'int',
        'track_inventory'     => 'bool',
        'weight_grams'        => '?int',
        'is_giftbox_eligible' => 'bool',
        'giftbox_slots'       => 'int',
        'is_featured'         => 'bool',
        'is_active'           => 'bool',
        'sort_order'          => 'int',
    ];

    public function url(): string
    {
        return site_url('product/' . $this->attributes['slug']);
    }

    public function primaryImage(): ?string
    {
        // Set by ProductModel::withPrimaryImage(); null when not selected.
        return $this->attributes['primary_image'] ?? null;
    }

    public function imageUrl(): string
    {
        return rs_image($this->primaryImage(), 'products');
    }

    public function inStock(): bool
    {
        if (! $this->track_inventory) {
            return true;
        }

        return $this->stock_qty > 0;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory
            && $this->stock_qty > 0
            && $this->stock_qty <= $this->low_stock_threshold;
    }

    public function stockLabel(): string
    {
        if (! $this->inStock()) {
            return 'Sold out';
        }

        if ($this->isLowStock()) {
            return $this->stock_qty === 1 ? 'Last one' : 'Only ' . $this->stock_qty . ' left';
        }

        return 'In stock';
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): int
    {
        if (! $this->hasDiscount() || $this->compare_at_price <= 0) {
            return 0;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    public function formattedPrice(): string
    {
        return rs_money($this->price);
    }

    public function formattedCompareAtPrice(): ?string
    {
        return $this->hasDiscount() ? rs_money($this->compare_at_price) : null;
    }

    /**
     * Which journey applies to this product: its own pin, or the site switch.
     * Presentation only — checkout re-resolves this server-side.
     */
    public function saleMode(): string
    {
        return service('settings')->resolveItemMode($this->attributes['sale_mode'] ?? null);
    }

    public function isEnquireOnly(): bool
    {
        return $this->saleMode() === Rasmein::MODE_ENQUIRE;
    }

    public function ctaLabel(string $variant = 'add'): string
    {
        return rs_cta_label($this->saleMode(), $variant);
    }
}
