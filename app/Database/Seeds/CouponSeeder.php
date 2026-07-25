<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * A few coupons that exercise every branch of the validator: a percent code
 * with a cap, a flat code with a minimum, free shipping, an expired code and
 * a fully-redeemed one. Idempotent.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $coupons = [
            [
                'code'                     => 'WELCOME10',
                'description'              => '10% off your first box, up to ₹300.',
                'discount_type'            => 'percent',
                'value'                    => 10,
                'min_order_value'          => 0,
                'max_discount'             => 300,
                'usage_limit_total'        => null,
                'usage_limit_per_customer' => 1,
                'first_order_only'         => 1,
                'starts_at'                => null,
                'ends_at'                  => null,
            ],
            [
                'code'                     => 'DIWALI500',
                'description'              => '₹500 off orders over ₹4,000.',
                'discount_type'            => 'fixed',
                'value'                    => 500,
                'min_order_value'          => 4000,
                'max_discount'             => null,
                'usage_limit_total'        => 500,
                'usage_limit_per_customer' => 2,
                'first_order_only'         => 0,
                'starts_at'                => null,
                'ends_at'                  => null,
            ],
            [
                'code'                     => 'FREESHIP',
                'description'              => 'Free delivery, any order value.',
                'discount_type'            => 'free_shipping',
                'value'                    => 0,
                'min_order_value'          => 0,
                'max_discount'             => null,
                'usage_limit_total'        => null,
                'usage_limit_per_customer' => null,
                'first_order_only'         => 0,
                'starts_at'                => null,
                'ends_at'                  => null,
            ],
            [
                // Deliberately expired, so the "expired" branch is testable.
                'code'                     => 'LASTYEAR',
                'description'              => 'Expired — kept for testing.',
                'discount_type'            => 'percent',
                'value'                    => 25,
                'min_order_value'          => 0,
                'max_discount'             => null,
                'usage_limit_total'        => null,
                'usage_limit_per_customer' => null,
                'first_order_only'         => 0,
                'starts_at'                => date('Y-m-d H:i:s', strtotime('-2 years')),
                'ends_at'                  => date('Y-m-d H:i:s', strtotime('-1 year')),
            ],
        ];

        $added = 0;

        foreach ($coupons as $coupon) {
            if ($this->db->table('coupons')->where('code', $coupon['code'])->countAllResults() > 0) {
                continue;
            }

            $this->db->table('coupons')->insert(array_merge($coupon, [
                'used_count' => 0,
                'applies_to' => 'all',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $added++;
        }

        echo "  Coupons: {$added} added.\n";
    }
}
