<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Variants: a sellable combination of attribute values.
 *
 * WHY THIS EXISTS ON TOP OF ATTRIBUTES
 *
 * Attributes alone say "this piece comes in silver and antique silver, round,
 * with an antique finish" — a bag of facts with no relationship between them.
 * They cannot answer "is antique silver available in the large size", cannot
 * price one colour higher, and cannot give a colour its own photograph.
 *
 * A VARIANT is one combination that can actually be bought. Selecting a colour
 * narrows what else is offered, because only combinations that exist are
 * listed. Everything a buyer cares about — price, stock, picture, description —
 * hangs off the variant and falls back to the product when it is not set.
 *
 * `variant_key` is the URL segment: /product/{slug}/{key}. Stored rather than
 * derived so it survives a value being renamed — a shared link must not break
 * because someone edited "Silver" to "Sterling Silver".
 */
class ProductVariants extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sku'        => ['type' => 'VARCHAR', 'constraint' => 80],
            // The URL segment, e.g. "silver-9-5x9-5".
            'variant_key' => ['type' => 'VARCHAR', 'constraint' => 120],
            // Human label for the cart and the order: "Silver · 9.5\" x 9.5\"".
            'label'      => ['type' => 'VARCHAR', 'constraint' => 191],

            /*
             * All NULL-able and all overrides. A variant that costs the same,
             * looks the same and reads the same as its product should not have
             * to repeat any of it — and if the product's price changes, every
             * variant that never set its own should follow.
             */
            'price'            => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'compare_at_price' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'image'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // Stock is per variant: silver can sell out while gold does not.
            'stock_qty'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        // The key only has to be unique WITHIN a product — two products may
        // both have a "silver".
        $this->forge->addUniqueKey(['product_id', 'variant_key']);
        $this->forge->addForeignKey('product_id', 'products', 'id', '', 'CASCADE');
        $this->forge->createTable('product_variants', true);

        /*
         * Which attribute values make up each variant.
         *
         * A row per value, not a serialised list, because the product page has
         * to ask "which variants have value 7" to decide what stays selectable
         * — and that is a join, not a string search.
         */
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'value_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['variant_id', 'value_id']);
        $this->forge->addKey('value_id');
        $this->forge->addForeignKey('variant_id', 'product_variants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('value_id', 'attribute_values', 'id', '', 'CASCADE');
        $this->forge->createTable('variant_values', true);

        // The cart remembers which variant, not just which product.
        $this->forge->addColumn('cart_items', [
            'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);

        // The order keeps a TEXT snapshot as well as the id, so it still reads
        // correctly after a variant is renamed or removed.
        $this->forge->addColumn('order_items', [
            'variant_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'variant_label' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('order_items', ['variant_id', 'variant_label']);
        $this->forge->dropColumn('cart_items', 'variant_id');
        $this->forge->dropTable('variant_values', true);
        $this->forge->dropTable('product_variants', true);
    }
}
