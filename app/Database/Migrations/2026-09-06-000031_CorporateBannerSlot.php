<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A banner slot for the corporate page.
 *
 * `banners.position` is a database ENUM, so widening the model's `in_list` rule
 * was not enough — MySQL rejected the insert outright. A validation rule
 * describes what the application accepts; the column decides what the database
 * will hold, and both have to agree.
 */
class CorporateBannerSlot extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('banners', [
            'position' => [
                'name'       => 'position',
                'type'       => 'ENUM',
                'constraint' => [
                    'home_hero', 'corporate_hero', 'home_strip', 'home_feature',
                    'home_client', 'home_gallery', 'category_top', 'gift_builder',
                ],
                'default' => 'home_hero',
                'null'    => false,
            ],
        ]);
    }

    public function down(): void
    {
        // Anything sitting in the slot being removed would violate the narrower
        // ENUM, so it moves to the home hero rather than blocking the rollback.
        $this->db->table('banners')->where('position', 'corporate_hero')
            ->update(['position' => 'home_hero']);

        $this->forge->modifyColumn('banners', [
            'position' => [
                'name'       => 'position',
                'type'       => 'ENUM',
                'constraint' => [
                    'home_hero', 'home_strip', 'home_feature',
                    'home_client', 'home_gallery', 'category_top', 'gift_builder',
                ],
                'default' => 'home_hero',
                'null'    => false,
            ],
        ]);
    }
}
