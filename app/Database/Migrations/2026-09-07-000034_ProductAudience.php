<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Which shop a product belongs on.
 *
 * The same idea as `collections.audience`, and deliberately the same three
 * values: a bulk desk set is corporate, a personalised keepsake is retail, and
 * most pieces are simply both.
 *
 * `both` is the default so nothing already in the catalogue disappears from a
 * page it currently sits on — 155 products silently vanishing from the shop is
 * not an acceptable cost for adding a filter.
 */
class ProductAudience extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'audience' => [
                'type'       => 'ENUM',
                'constraint' => ['both', 'retail', 'corporate'],
                'default'    => 'both',
                'after'      => 'sale_mode',
            ],
        ]);

        // Every listing filters on it, so it earns an index.
        $this->db->query('CREATE INDEX products_audience ON products (audience)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX products_audience ON products');
        $this->forge->dropColumn('products', 'audience');
    }
}
