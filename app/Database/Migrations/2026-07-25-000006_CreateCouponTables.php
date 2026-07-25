<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Discount codes.
 *
 * The discount amount is never accepted from the client. At checkout the
 * server re-reads the coupon, re-checks window / limits / eligibility, and
 * recomputes the value. `coupon_redemptions` is the ledger that enforces
 * usage limits and gives reporting a per-order record.
 */
class CreateCouponTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + $this->str('code', 40)
            + $this->str('description', 255, true)
            + $this->enum('discount_type', ['percent', 'fixed', 'free_shipping'], 'percent')
            + $this->money('value', false, '0.00', '10,2')
            + $this->money('min_order_value', false, '0.00', '10,2')
            + $this->money('max_discount', true, '0.00', '10,2')  // caps a percent coupon
            + $this->int('usage_limit_total', 0, true)            // null = unlimited
            + $this->int('usage_limit_per_customer', 1, true)
            + $this->int('used_count')
            + $this->enum('applies_to', ['all', 'products', 'categories', 'gift_boxes'], 'all')
            + $this->flag('first_order_only', 0)
            + $this->datetime('starts_at')
            + $this->datetime('ends_at')
            + $this->flag('is_active')
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey(['is_active', 'starts_at', 'ends_at']);
        $this->forge->createTable('coupons', false, $this->tableAttributes);

        // -------------------------------------------------- restrictions
        $this->forge->addField(
            $this->pk()
            + $this->ref('coupon_id')
            + $this->enum('restriction_type', ['product', 'category', 'gift_box'], 'product')
            + $this->ref('reference_id')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['coupon_id', 'restriction_type', 'reference_id'], 'uq_coupon_restriction');
        $this->forge->addForeignKey('coupon_id', 'coupons', 'id', 'CASCADE', 'CASCADE', 'fk_cr_coupon');
        $this->forge->createTable('coupon_restrictions', false, $this->tableAttributes);

        // --------------------------------------------------- redemptions
        // order_id has no FK yet — `orders` is created in the next migration.
        // The constraint is added there, once the target table exists.
        $this->forge->addField(
            $this->pk()
            + $this->ref('coupon_id')
            + $this->ref('order_id', true)
            + $this->ref('customer_id', true)
            + $this->str('email', 191, true)
            + $this->money('discount_amount', false, '0.00', '10,2')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['coupon_id', 'customer_id']);
        $this->forge->addKey('email');
        $this->forge->addForeignKey('coupon_id', 'coupons', 'id', 'CASCADE', 'CASCADE', 'fk_cred_coupon');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'SET NULL', 'fk_cred_customer');
        $this->forge->createTable('coupon_redemptions', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('coupon_redemptions', true);
        $this->forge->dropTable('coupon_restrictions', true);
        $this->forge->dropTable('coupons', true);
    }
}
