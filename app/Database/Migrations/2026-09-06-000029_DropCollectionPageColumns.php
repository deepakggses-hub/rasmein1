<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remove `collections.hero_image` and `collections.data`.
 *
 * They belonged to a first approach — a rich landing page per collection —
 * which was replaced by the `collections` PAGE template. Nothing reads either
 * column now, and dead schema is worse than no schema: the next person to open
 * the table has to work out which of two mechanisms is the live one.
 *
 * `down()` puts them back empty rather than restoring content, which is the
 * honest thing a reversal can promise here.
 */
class DropCollectionPageColumns extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        foreach (['hero_image', 'data'] as $column) {
            // Guarded: this ran on installs that never had the columns, and a
            // migration that throws on a fresh database is a migration nobody
            // can run.
            if ($db->fieldExists($column, 'collections')) {
                $this->forge->dropColumn('collections', $column);
            }
        }
    }

    public function down(): void
    {
        $this->forge->addColumn('collections', [
            'hero_image' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'data'       => ['type' => 'JSON', 'null' => true],
        ]);
    }
}
