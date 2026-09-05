<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rich landing pages for a collection or occasion.
 *
 * `data` holds whatever the layout needs, as JSON — the same shape used for
 * page templates. A column per field would mean a migration every time a
 * section is added, and these are read as one whole and never queried across
 * collections.
 */
class CollectionPages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('collections', [
            'hero_image' => [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'image',
            ],
            'data' => [
                'type' => 'JSON', 'null' => true, 'after' => 'description',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('collections', ['hero_image', 'data']);
    }
}
