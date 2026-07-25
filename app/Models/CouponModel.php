<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class CouponModel extends Model
{
    protected $table          = 'coupons';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'code', 'description', 'discount_type', 'value', 'min_order_value',
        'max_discount', 'usage_limit_total', 'usage_limit_per_customer',
        'used_count', 'applies_to', 'first_order_only',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected $validationRules = [
        'code'          => 'required|max_length[40]|regex_match[/^[A-Z0-9_-]+$/]|is_unique[coupons.code,id,{id}]',
        'discount_type' => 'required|in_list[percent,fixed,free_shipping]',
        'value'         => 'required|decimal|greater_than_equal_to[0]',
        'applies_to'    => 'required|in_list[all,products,categories,gift_boxes]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'A coupon code may contain only capital letters, numbers, hyphens and underscores.',
        ],
    ];

    /** Codes are matched case-insensitively but stored uppercase. */
    public function findByCode(string $code): ?array
    {
        return $this->where('code', strtoupper(trim($code)))->first();
    }

    /** How many times this email/customer has already redeemed a coupon. */
    public function redemptionCount(int $couponId, ?int $customerId, ?string $email): int
    {
        $builder = $this->db->table('coupon_redemptions')->where('coupon_id', $couponId);

        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        } elseif ($email !== null && $email !== '') {
            $builder->where('email', $email);
        } else {
            return 0;
        }

        return $builder->countAllResults();
    }

    /** @return list<int> Reference ids this coupon is restricted to. */
    public function restrictionIds(int $couponId, string $type): array
    {
        $rows = $this->db->table('coupon_restrictions')
            ->select('reference_id')
            ->where('coupon_id', $couponId)
            ->where('restriction_type', $type)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r): int => (int) $r['reference_id'], $rows);
    }
}
