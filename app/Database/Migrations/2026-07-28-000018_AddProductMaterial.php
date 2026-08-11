<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Material, so the listing page can filter on it.
 *
 * The design shows a Material facet — Brass, Silk, Linen, Wood — and there was
 * no column behind it. A filter with nothing to filter on is worse than no
 * filter, so this adds the field rather than faking the sidebar.
 *
 * A plain string, not a lookup table: a shop wants to type "Bhagalpur linen"
 * without an administrator first creating it, and the facet is built from the
 * distinct values actually in use.
 */
class AddProductMaterial extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'material' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'unit_label',
            ],
        ]);

        // Indexed because every facet count groups by it.
        $this->db->query('CREATE INDEX idx_products_material ON products (material)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_products_material ON products');
        $this->forge->dropColumn('products', 'material');
    }
}
