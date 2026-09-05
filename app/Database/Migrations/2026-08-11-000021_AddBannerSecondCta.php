<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A second button for banners.
 *
 * The hero design has two — "Explore collection" and "Build your gift box" —
 * but only the first was ever a field. The second was hard-coded to /build in
 * the template, so a shop could not change its wording or where it went, and
 * could not remove it. Both buttons are now data.
 */
class AddBannerSecondCta extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('banners', [
            'cta_label_2' => [
                'type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'cta_label',
            ],
            'link_url_2' => [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'cta_label_2',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('banners', ['cta_label_2', 'link_url_2']);
    }
}
