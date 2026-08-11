<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Testimonials, and three more banner slots for the homepage.
 *
 * The homepage design has a "words that warm us" section. There was nowhere to
 * put that copy — squeezing it into `banners` (quote in the title, name in the
 * subtitle) would have worked for a week and confused everyone after that. A
 * quote with an author, a role and a rating is its own shape.
 */
class AddTestimonials extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'quote'      => ['type' => 'TEXT'],
            'author'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'rating'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 5],
            'image'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('testimonials');
    }

    public function down(): void
    {
        $this->forge->dropTable('testimonials');
    }
}
