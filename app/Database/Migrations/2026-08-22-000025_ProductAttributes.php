<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Product attributes: colour, size, shape, finish, capacity.
 *
 * MODELLED AS SPECIFICATIONS, NOT VARIANTS
 *
 * The catalogues show "COLOR VARIANT" and "SIZE 8x3.5x5" as descriptions of ONE
 * product with one price ladder — not as separate SKUs each with their own
 * stock and price. Building full variants would mean a row per combination,
 * a stock count per row, and a price per row, none of which the catalogue has
 * and none of which the shop tracks.
 *
 * So: an attribute describes a product, and MAY be selectable if the customer
 * needs to state a preference. `is_selectable` is the difference between "this
 * bowl is 8 inches" and "which colour would you like".
 *
 * Values are a controlled list per attribute rather than free text, because
 * "Silver", "silver" and "SILVER" are three facets otherwise.
 */
class ProductAttributes extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------- attributes
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            // Stable handle for query strings and code — the name can be
            // renamed without breaking every saved filter link.
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            /*
             * `swatch` draws a colour chip, `text` a plain chip. Kept as an
             * ENUM: a display style the views must switch on cannot be
             * open-ended, or a typo silently renders nothing.
             */
            'input_type'    => ['type' => 'ENUM', 'constraint' => ['swatch', 'text'], 'default' => 'text'],
            // Shown as a chooser on the product page, versus listed as a spec.
            'is_selectable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // Offered as a filter on listing pages.
            'is_filterable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('attributes', true);

        // ----------------------------------------------------- attribute values
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'attribute_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'label'        => ['type' => 'VARCHAR', 'constraint' => 80],
            // For a swatch. Null on a text value.
            'swatch_hex'   => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
            'sort_order'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('attribute_id');
        // One "Silver" per attribute. Two would split the facet in half.
        $this->forge->addUniqueKey(['attribute_id', 'label']);
        $this->forge->addForeignKey('attribute_id', 'attributes', 'id', '', 'CASCADE');
        $this->forge->createTable('attribute_values', true);

        // --------------------------------------------------- the join to products
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'value_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // A product cannot carry the same value twice.
        $this->forge->addUniqueKey(['product_id', 'value_id']);
        $this->forge->addKey('value_id');
        $this->forge->addForeignKey('product_id', 'products', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('value_id', 'attribute_values', 'id', '', 'CASCADE');
        $this->forge->createTable('product_attributes', true);

        /*
         * What the customer chose, on the cart line.
         *
         * Free text rather than a foreign key: an order is a RECORD of what was
         * agreed. If a value is renamed or deleted a year later, the order must
         * still read the way it did when it was placed.
         */
        $this->forge->addColumn('cart_items', [
            'chosen_attributes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'gift_box_id'],
        ]);
        // No `after`: it names a column that must exist on THIS table, and
        // guessing one is how an ALTER gets silently rejected.
        $this->forge->addColumn('order_items', [
            'chosen_attributes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('order_items', 'chosen_attributes');
        $this->forge->dropColumn('cart_items', 'chosen_attributes');
        $this->forge->dropTable('product_attributes', true);
        $this->forge->dropTable('attribute_values', true);
        $this->forge->dropTable('attributes', true);
    }
}
