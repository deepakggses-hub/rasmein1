<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class GiftBoxPricingRuleModel extends Model
{
    protected $table         = 'gift_box_pricing_rules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'gift_box_id', 'rule_type', 'value', 'min_slots', 'max_slots',
        'min_subtotal', 'label', 'priority', 'is_active',
    ];

    protected $validationRules = [
        'gift_box_id' => 'required|is_natural_no_zero',
        'rule_type'   => 'required|in_list[flat_box_price,percent_markup,slot_discount_percent,slot_discount_amount,waive_box_price]',
        'value'       => 'required|decimal|greater_than_equal_to[0]',
        'min_slots'   => 'permit_empty|is_natural',
        'max_slots'   => 'permit_empty|is_natural',
        'label'       => 'permit_empty|max_length[120]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function activeForBox(int $giftBoxId): array
    {
        return $this->where('gift_box_id', $giftBoxId)
            ->where('is_active', 1)
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
