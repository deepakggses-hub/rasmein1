<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * The Build-Your-Own-Gift-Box configuration.
 *
 * Capacity is counted in *slots* (compartments). A product consumes
 * `products.giftbox_slots` per unit. Which products may go in a box is
 * resolved as: allowed categories (or all, if none listed), plus explicitly
 * allowed products, minus explicitly excluded products.
 *
 * Every one of these rules is re-checked server-side when an order or
 * enquiry is created — the builder UI is not the enforcement point.
 */
class CreateGiftBoxTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // --------------------------------------------------- gift boxes
        $this->forge->addField(
            $this->pk()
            + $this->str('name', 120)
            + $this->str('slug', 160)
            + $this->text('description')
            + $this->str('image', 255, true)
            + $this->money('base_price', false, '0.00', '10,2')
            + $this->int('capacity_slots', 6)
            + $this->int('min_slots', 1)
            + $this->str('size_label', 60, true)    // "Small", "Grand"
            + $this->str('theme', 60, true)         // "Festive", "Corporate"
            + $this->str('price_tier', 40, true)    // "Under 1500"
            + $this->enum('sale_mode', ['inherit', 'buy_now', 'enquire_now'], 'inherit')
            + $this->flag('allow_gift_message')
            + $this->int('gift_message_max_chars', 300)
            + $this->flag('allow_special_note')
            + $this->flag('is_featured', 0)
            + $this->int('sort_order')
            + $this->flag('is_active')
            + $this->seoFields()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('gift_boxes', false, $this->tableAttributes);

        // ------------------------------------------ allowed categories
        $this->forge->addField(
            $this->pk()
            + $this->ref('gift_box_id')
            + $this->ref('category_id')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['gift_box_id', 'category_id']);
        $this->forge->addForeignKey('gift_box_id', 'gift_boxes', 'id', 'CASCADE', 'CASCADE', 'fk_gbc_box');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'CASCADE', 'fk_gbc_category');
        $this->forge->createTable('gift_box_categories', false, $this->tableAttributes);

        // -------------------------------- product allow / exclude list
        $this->forge->addField(
            $this->pk()
            + $this->ref('gift_box_id')
            + $this->ref('product_id')
            // 0 = explicitly allowed, 1 = explicitly excluded (exclusion wins)
            + $this->flag('is_excluded', 0)
            + $this->int('max_quantity', 0, true)   // null = no per-box cap
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['gift_box_id', 'product_id']);
        $this->forge->addForeignKey('gift_box_id', 'gift_boxes', 'id', 'CASCADE', 'CASCADE', 'fk_gbp_box');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_gbp_product');
        $this->forge->createTable('gift_box_products', false, $this->tableAttributes);

        // ----------------------------------------------- pricing rules
        $this->forge->addField(
            $this->pk()
            + $this->ref('gift_box_id')
            + $this->enum('rule_type', [
                'flat_box_price',        // box costs a fixed amount
                'percent_markup',        // add % on top of contents
                'slot_discount_percent', // % off contents once slot band is met
                'slot_discount_amount',  // flat off contents
                'waive_box_price',       // box price becomes 0 above a subtotal
            ], 'flat_box_price')
            + $this->money('value', false, '0.00', '10,2')
            + $this->int('min_slots', 0, true)
            + $this->int('max_slots', 0, true)
            + $this->money('min_subtotal', true, '0.00', '10,2')
            + $this->str('label', 120, true)        // shown in the cart breakdown
            + $this->int('priority')
            + $this->flag('is_active')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['gift_box_id', 'is_active', 'priority']);
        $this->forge->addForeignKey('gift_box_id', 'gift_boxes', 'id', 'CASCADE', 'CASCADE', 'fk_gbpr_box');
        $this->forge->createTable('gift_box_pricing_rules', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('gift_box_pricing_rules', true);
        $this->forge->dropTable('gift_box_products', true);
        $this->forge->dropTable('gift_box_categories', true);
        $this->forge->dropTable('gift_boxes', true);
    }
}
