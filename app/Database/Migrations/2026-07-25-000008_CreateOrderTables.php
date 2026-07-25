<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Orders — the single record of a completed customer journey.
 *
 * DESIGN NOTE (important, read before adding an "enquiries" line-item table):
 * A Buy order and an Enquiry are the same shape. Both are a set of line items
 * with a contact and an address. They differ only in what happens next: one
 * takes a payment, the other enters a sales pipeline. So there is ONE `orders`
 * table with a `journey_mode` column, and a separate `enquiries` table
 * (migration 009) that hangs the lead-tracking fields off an order row.
 *
 * That avoids maintaining two parallel sets of item/component tables that
 * would inevitably drift apart, and it means reporting on "everything a
 * customer asked for" is one query.
 *
 * Admin > Orders   filters journey_mode = buy_now
 * Admin > Enquiries filters journey_mode = enquire_now
 *
 * Every *_snapshot / name_snapshot column is written once at order time and
 * never updated: an invoice must still be correct after a product is renamed,
 * repriced, or deleted.
 */
class CreateOrderTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + $this->str('order_ref', 32)                                  // RSM-2026-000123
            + ['uuid' => ['type' => 'CHAR', 'constraint' => 36]]           // public-facing, unguessable
            + $this->ref('customer_id', true)
            + $this->ref('cart_id', true)
            + $this->enum('journey_mode', ['buy_now', 'enquire_now'], 'buy_now')
            + $this->enum('status', [
                'pending', 'confirmed', 'processing', 'packed',
                'dispatched', 'delivered', 'cancelled', 'refunded',
            ], 'pending')
            + $this->enum('payment_status', [
                'not_applicable', 'unpaid', 'pending', 'paid', 'failed', 'refunded',
            ], 'not_applicable')
            + $this->str('payment_method', 40, true)
            // Guards double-submit and retried webhooks (see CLAUDE.md §8).
            + $this->str('idempotency_key', 64, true)

            // ------- money: all recomputed server-side before insert -------
            + ['currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR']]
            + $this->money('subtotal')
            + $this->money('discount_total')
            + $this->money('shipping_total')
            + $this->money('tax_total')
            + $this->money('grand_total')
            + $this->ref('coupon_id', true)
            + $this->str('coupon_code', 40, true)

            // ------------------------- contact -------------------------
            + $this->str('customer_name', 120)
            + $this->str('customer_email', 191)
            + $this->str('customer_phone', 20)

            // ------------------------ shipping ------------------------
            + $this->str('ship_name', 120, true)
            + $this->str('ship_phone', 20, true)
            + $this->str('ship_line1', 191, true)
            + $this->str('ship_line2', 191, true)
            + $this->str('ship_landmark', 120, true)
            + $this->str('ship_city', 80, true)
            + $this->str('ship_state', 80, true)
            + $this->str('ship_postal_code', 12, true)
            + $this->str('ship_country', 60, true)

            // ------------------------- billing -------------------------
            + $this->flag('bill_same_as_ship')
            + $this->str('bill_name', 120, true)
            + $this->str('bill_line1', 191, true)
            + $this->str('bill_line2', 191, true)
            + $this->str('bill_city', 80, true)
            + $this->str('bill_state', 80, true)
            + $this->str('bill_postal_code', 12, true)
            + $this->str('bill_country', 60, true)
            + $this->str('bill_gstin', 20, true)

            // -------------------------- notes --------------------------
            + $this->str('gift_message', 500, true)
            + $this->text('customer_note')
            + $this->text('admin_note')

            // ------------------------ lifecycle ------------------------
            + $this->datetime('placed_at')
            + $this->datetime('confirmed_at')
            + $this->datetime('dispatched_at')
            + $this->datetime('delivered_at')
            + $this->datetime('cancelled_at')
            + $this->requestFingerprint()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('order_ref');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey(['journey_mode', 'status']);
        $this->forge->addKey(['payment_status', 'placed_at']);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('customer_email');
        $this->forge->addKey('placed_at');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'SET NULL', 'fk_orders_customer');
        $this->forge->addForeignKey('coupon_id', 'coupons', 'id', 'CASCADE', 'SET NULL', 'fk_orders_coupon');
        $this->forge->createTable('orders', false, $this->tableAttributes);

        // Back-fill the two constraints that pointed at `orders` before it existed.
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'SET NULL', 'fk_cred_order');
        $this->forge->processIndexes('coupon_redemptions');

        $this->forge->addForeignKey('converted_order_id', 'orders', 'id', 'CASCADE', 'SET NULL', 'fk_carts_order');
        $this->forge->processIndexes('carts');

        // -------------------------------------------------- order items
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_id')
            + $this->enum('item_type', ['product', 'gift_box'], 'product')
            + $this->ref('product_id', true)
            + $this->ref('gift_box_id', true)
            + $this->str('name_snapshot', 191)
            + $this->str('sku_snapshot', 60, true)
            + $this->money('unit_price', false, '0.00', '10,2')
            + $this->int('quantity', 1)
            + $this->money('line_total')
            + $this->int('slots_used')
            + $this->str('gift_recipient', 120, true)
            + $this->str('gift_message', 500, true)
            + $this->str('special_note', 500, true)
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE', 'fk_oi_order');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'SET NULL', 'fk_oi_product');
        $this->forge->addForeignKey('gift_box_id', 'gift_boxes', 'id', 'CASCADE', 'SET NULL', 'fk_oi_box');
        $this->forge->createTable('order_items', false, $this->tableAttributes);

        // --------------------------- contents of a gift-box order line
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_item_id')
            + $this->ref('product_id', true)
            + $this->str('name_snapshot', 191)
            + $this->str('sku_snapshot', 60, true)
            + $this->money('unit_price', false, '0.00', '10,2')
            + $this->int('quantity', 1)
            + $this->int('slots_used', 1)
            + $this->money('line_total')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_item_id');
        $this->forge->addForeignKey('order_item_id', 'order_items', 'id', 'CASCADE', 'CASCADE', 'fk_oic_item');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'SET NULL', 'fk_oic_product');
        $this->forge->createTable('order_item_components', false, $this->tableAttributes);

        // ------------------------------------------------ status history
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_id')
            + $this->str('from_status', 30, true)
            + $this->str('to_status', 30)
            + $this->str('note', 500, true)
            + $this->ref('changed_by_admin_id', true)
            + $this->flag('notified_customer', 0)
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['order_id', 'created_at']);
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE', 'fk_osh_order');
        $this->forge->addForeignKey('changed_by_admin_id', 'admin_users', 'id', 'CASCADE', 'SET NULL', 'fk_osh_admin');
        $this->forge->createTable('order_status_history', false, $this->tableAttributes);

        // ---------------------------------- shipments (manual dispatch)
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_id')
            + $this->str('courier_name', 120, true)
            + $this->str('tracking_number', 120, true)
            + $this->str('tracking_url', 255, true)
            + $this->datetime('packed_at')
            + $this->datetime('dispatched_at')
            + $this->datetime('delivered_at')
            + $this->str('note', 500, true)
            + $this->ref('created_by_admin_id', true)
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('tracking_number');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE', 'fk_ship_order');
        $this->forge->addForeignKey('created_by_admin_id', 'admin_users', 'id', 'CASCADE', 'SET NULL', 'fk_ship_admin');
        $this->forge->createTable('shipments', false, $this->tableAttributes);

        // ----------------------------------------------------- payments
        // Created now, unused until the payment-gateway phase. No card data
        // is ever stored here — only gateway references and status.
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_id')
            + $this->str('gateway', 40, false, 'razorpay')
            + $this->str('gateway_order_id', 120, true)
            + $this->str('gateway_payment_id', 120, true)
            + $this->flag('signature_verified', 0)
            + $this->money('amount')
            + ['currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR']]
            + $this->enum('status', [
                'created', 'authorized', 'captured', 'failed', 'refunded',
            ], 'created')
            + $this->str('failure_reason', 255, true)
            + $this->text('raw_response')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('gateway_payment_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('gateway_order_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE', 'fk_pay_order');
        $this->forge->createTable('payments', false, $this->tableAttributes);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->forge->dropForeignKey('carts', 'fk_carts_order');
            $this->forge->dropForeignKey('coupon_redemptions', 'fk_cred_order');
        }

        $this->forge->dropTable('payments', true);
        $this->forge->dropTable('shipments', true);
        $this->forge->dropTable('order_status_history', true);
        $this->forge->dropTable('order_item_components', true);
        $this->forge->dropTable('order_items', true);
        $this->forge->dropTable('orders', true);
    }
}
