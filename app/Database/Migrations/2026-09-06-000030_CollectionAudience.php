<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Who an occasion is for.
 *
 * The corporate page shows work occasions — onboarding, a work anniversary — and
 * the ordinary shop shows festivals. Some belong on both: Diwali is gifted to a
 * client as readily as to a cousin.
 *
 * `both` is the default so nothing already in the table disappears from a page
 * it currently sits on.
 */
class CollectionAudience extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('collections', [
            'audience' => [
                'type'       => 'ENUM',
                'constraint' => ['both', 'retail', 'corporate'],
                'default'    => 'both',
                'after'      => 'type',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('collections', 'audience');
    }
}
