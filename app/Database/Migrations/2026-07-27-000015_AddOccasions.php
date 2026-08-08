<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Occasions.
 *
 * WHY THIS EXTENDS `collections` RATHER THAN ADDING A TABLE
 *
 * An occasion is the same thing a collection already is: a named set of
 * products with a slug, an image and a landing page. "Diwali 2026" was already
 * seeded AS a collection. Adding a parallel `occasions` table would duplicate
 * the model, the pivot, the CRUD, the landing page and — most dangerously — the
 * check that stops two things claiming the same root URL. Two copies of that
 * check is how they drift.
 *
 * So one table, one pivot, one resolver, with a `type` column deciding how it
 * is presented and where it lives. The admin still sees "Occasions" and
 * "Collections" as separate screens, which is what was asked for.
 *
 * `starts_at`/`ends_at` exist because an occasion is usually seasonal — Diwali
 * runs for a few weeks — and a shop should be able to set that up in advance
 * rather than remembering to switch it on.
 */
class AddOccasions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('collections', [
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['collection', 'occasion'],
                'default'    => 'collection',
                'null'       => false,
                'after'      => 'id',
            ],
            'starts_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_active'],
            'ends_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'starts_at'],
        ]);

        $this->db->query('CREATE INDEX idx_collections_type ON collections (type, is_active, sort_order)');

        // The one already seeded as a collection is plainly an occasion.
        $this->db->query("UPDATE collections SET type = 'occasion' WHERE slug LIKE 'diwali%'");
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_collections_type ON collections');
        $this->forge->dropColumn('collections', ['type', 'starts_at', 'ends_at']);
    }
}
