<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Categories, products, product images, and curated collections.
 *
 * `sale_mode` on a product is the per-product half of the dual journey:
 * 'inherit' follows the site-wide switch, otherwise the product is pinned
 * to Buy or Enquire — one at a time, never both.
 */
class CreateCatalogueTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // -------------------------------------------------- categories
        $this->forge->addField(
            $this->pk()
            + $this->ref('parent_id', true)
            + $this->str('name', 120)
            + $this->str('slug', 160)
            + $this->text('description')
            + $this->str('image', 255, true)
            + $this->flag('is_featured', 0)
            + $this->int('sort_order')
            + $this->flag('is_active')
            + $this->seoFields()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('parent_id');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->addForeignKey('parent_id', 'categories', 'id', 'CASCADE', 'SET NULL', 'fk_categories_parent');
        $this->forge->createTable('categories', false, $this->tableAttributes);

        // ---------------------------------------------------- products
        $this->forge->addField(
            $this->pk()
            + $this->ref('category_id', true)
            + $this->str('sku', 60)
            + $this->str('name', 191)
            + $this->str('slug', 200)
            + $this->str('short_description', 255, true)
            + $this->text('description')
            + $this->money('price', false, '0.00', '10,2')
            + $this->money('compare_at_price', true, '0.00', '10,2')
            + $this->int('stock_qty')
            + $this->int('low_stock_threshold', 5)
            + $this->flag('track_inventory')
            + $this->int('weight_grams', 0, true)
            + $this->str('unit_label', 40, true)   // "250 g", "pack of 4"
            + $this->enum('sale_mode', ['inherit', 'buy_now', 'enquire_now'], 'inherit')
            // Gift-box eligibility and how many compartments one unit fills.
            + $this->flag('is_giftbox_eligible')
            + $this->int('giftbox_slots', 1)
            + $this->flag('is_featured', 0)
            + $this->flag('is_active')
            + $this->int('sort_order')
            + $this->seoFields()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sku');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('category_id');
        $this->forge->addKey(['is_active', 'is_featured']);
        $this->forge->addKey(['is_active', 'is_giftbox_eligible']);
        $this->forge->addKey('price');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'SET NULL', 'fk_products_category');
        $this->forge->createTable('products', false, $this->tableAttributes);

        // Full-text index for storefront search. MySQL/MariaDB only, so it is
        // added as raw SQL rather than through Forge.
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE `products` ADD FULLTEXT `ft_products_search` (`name`, `short_description`, `description`)');
        }

        // ---------------------------------------------- product images
        $this->forge->addField(
            $this->pk()
            + $this->ref('product_id')
            + $this->str('path', 255)
            + $this->str('alt_text', 191, true)
            + $this->flag('is_primary', 0)
            + $this->int('sort_order')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['product_id', 'sort_order']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_product_images_product');
        $this->forge->createTable('product_images', false, $this->tableAttributes);

        // ------------------------------------------------- collections
        $this->forge->addField(
            $this->pk()
            + $this->str('name', 120)
            + $this->str('slug', 160)
            + $this->text('description')
            + $this->str('image', 255, true)
            + $this->flag('is_featured', 0)
            + $this->int('sort_order')
            + $this->flag('is_active')
            + $this->seoFields()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('collections', false, $this->tableAttributes);

        // ----------------------------------------- collection ↔ product
        $this->forge->addField(
            $this->pk()
            + $this->ref('collection_id')
            + $this->ref('product_id')
            + $this->int('sort_order')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['collection_id', 'product_id']);
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('collection_id', 'collections', 'id', 'CASCADE', 'CASCADE', 'fk_cp_collection');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_cp_product');
        $this->forge->createTable('collection_products', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('collection_products', true);
        $this->forge->dropTable('collections', true);
        $this->forge->dropTable('product_images', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('categories', true);
    }
}
