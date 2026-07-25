<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Server-side cart. Deliberately not a session array.
 *
 * Keeping the cart in the database means: a guest cart survives a browser
 * restart, an abandoned cart is reportable, a built gift box can be edited
 * later, and — most importantly — the server owns the line items so nothing
 * about price or capacity depends on what the browser sends back.
 *
 * The *_snapshot columns exist so the cart page can render without a join
 * storm. They are display values only. Checkout recomputes every figure
 * from products/gift_boxes/coupons before an order is written.
 */
class CreateCartTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + ['uuid' => ['type' => 'CHAR', 'constraint' => 36]]
            + $this->ref('customer_id', true)
            + $this->str('session_id', 128, true)
            + $this->enum('status', ['active', 'converted', 'abandoned'], 'active')
            + ['currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR']]
            + $this->datetime('last_activity_at')
            + $this->ref('converted_order_id', true)   // FK added in migration 008
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('session_id');
        $this->forge->addKey(['status', 'last_activity_at']);
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'SET NULL', 'fk_carts_customer');
        $this->forge->createTable('carts', false, $this->tableAttributes);

        // --------------------------------------------------- cart items
        $this->forge->addField(
            $this->pk()
            + $this->ref('cart_id')
            + $this->enum('item_type', ['product', 'gift_box'], 'product')
            + $this->ref('product_id', true)    // set when item_type = product
            + $this->ref('gift_box_id', true)   // set when item_type = gift_box
            + $this->int('quantity', 1)
            + $this->str('gift_recipient', 120, true)
            + $this->str('gift_message', 500, true)
            + $this->str('special_note', 500, true)
            + $this->money('unit_price_snapshot', false, '0.00', '10,2')
            + $this->money('line_total_snapshot', false, '0.00', '10,2')
            + $this->int('slots_used')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('cart_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('gift_box_id');
        $this->forge->addForeignKey('cart_id', 'carts', 'id', 'CASCADE', 'CASCADE', 'fk_cart_items_cart');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'SET NULL', 'fk_cart_items_product');
        $this->forge->addForeignKey('gift_box_id', 'gift_boxes', 'id', 'CASCADE', 'SET NULL', 'fk_cart_items_box');
        $this->forge->createTable('cart_items', false, $this->tableAttributes);

        // ----------------------------------- contents of a gift-box line
        $this->forge->addField(
            $this->pk()
            + $this->ref('cart_item_id')
            + $this->ref('product_id')
            + $this->int('quantity', 1)
            + $this->int('slots_used', 1)
            + $this->money('unit_price_snapshot', false, '0.00', '10,2')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['cart_item_id', 'product_id'], 'uq_cart_component');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('cart_item_id', 'cart_items', 'id', 'CASCADE', 'CASCADE', 'fk_cic_item');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_cic_product');
        $this->forge->createTable('cart_item_components', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('cart_item_components', true);
        $this->forge->dropTable('cart_items', true);
        $this->forge->dropTable('carts', true);
    }
}
