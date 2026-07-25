<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Config\Rasmein;

/**
 * A gift box the customer can fill. Capacity is measured in slots
 * (compartments); each product consumes `products.giftbox_slots` per unit.
 */
class GiftBox extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'                     => 'int',
        'base_price'             => 'float',
        'capacity_slots'         => 'int',
        'min_slots'              => 'int',
        'gift_message_max_chars' => 'int',
        'allow_gift_message'     => 'bool',
        'allow_special_note'     => 'bool',
        'is_featured'            => 'bool',
        'is_active'              => 'bool',
        'sort_order'             => 'int',
    ];

    public function url(): string
    {
        return site_url('gift-box/' . $this->attributes['slug']);
    }

    public function builderUrl(): string
    {
        return site_url('build/' . $this->attributes['slug']);
    }

    public function imageUrl(): string
    {
        return rs_image($this->attributes['image'] ?? null, 'boxes');
    }

    public function capacityLabel(): string
    {
        $slots = $this->capacity_slots;

        return $slots . ' ' . ($slots === 1 ? 'slot' : 'slots');
    }

    public function formattedBasePrice(): string
    {
        return $this->base_price > 0 ? rs_money($this->base_price) : 'Included';
    }

    public function saleMode(): string
    {
        return service('settings')->resolveItemMode($this->attributes['sale_mode'] ?? null);
    }

    public function isEnquireOnly(): bool
    {
        return $this->saleMode() === Rasmein::MODE_ENQUIRE;
    }

    public function ctaLabel(string $variant = 'primary'): string
    {
        return rs_cta_label($this->saleMode(), $variant);
    }
}
