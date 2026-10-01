<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A brochure lead no longer has to carry an email address.
 *
 * Name and phone are the mandatory pair; email is offered and kept when given.
 * For a gifting business taking bulk enquiries by phone that is the honest
 * shape — and a required field somebody will not fill in truthfully produces
 * worse data than an optional one they skip.
 *
 * NULL, not an empty string: "they did not give us one" and "they gave us an
 * empty one" are the same fact here, and only one of them should be
 * representable. The listing and the CSV both read it as absent.
 */
class BrochureLeadEmailOptional extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('brochure_leads', [
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
        ]);

        // Rows written while it was required cannot hold '' — but a later
        // import might, and the reader treats both as absent either way.
        $this->db->table('brochure_leads')->where('email', '')->update(['email' => null]);
    }

    public function down(): void
    {
        // Going back needs a value in every row, or the NOT NULL fails.
        $this->db->table('brochure_leads')->where('email', null)->update(['email' => '']);

        $this->forge->modifyColumn('brochure_leads', [
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false],
        ]);
    }
}
