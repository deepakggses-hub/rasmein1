<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enquiries from a content page.
 *
 * NOT the `enquiries` table. That one hangs off an ORDER — its `order_id` is a
 * required foreign key — because it tracks a quote for a basket someone has
 * already built. A lead from the story page has no basket and no order, and
 * forcing one into that shape would mean inventing an empty order per enquiry.
 *
 * Different thing, different table.
 */
class Leads extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ref'  => ['type' => 'VARCHAR', 'constraint' => 40],
            // Which page or form it came from, so a story-page lead is
            // tellable from a contact-page one without guessing.
            'source' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'website'],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['new', 'contacted', 'quoted', 'won', 'lost'],
                'default'    => 'new',
            ],
            'name'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'    => ['type' => 'VARCHAR', 'constraint' => 191],
            'phone'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'occasion' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'quantity' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'budget'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'message'  => ['type' => 'TEXT', 'null' => true],
            // Kept for rate limiting and for spotting a flood after the fact.
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('ref');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('leads', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('leads', true);
    }
}
