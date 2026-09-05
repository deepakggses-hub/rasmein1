<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sign-in by one-time code, and sign-in with Google.
 *
 * The shop's identity model changes here: a phone number becomes required
 * alongside an email, passwords stop being the way in, and an account can be
 * created either by verifying an email or by arriving from Google.
 *
 * `password_hash` is kept and left nullable rather than dropped. A column that
 * still holds hashes for existing accounts should not vanish in the same change
 * that stops using it — if any of this has to be rolled back, those accounts
 * still work.
 */
class AuthOverhaul extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE customers MODIFY password_hash VARCHAR(255) NULL');

        $this->forge->addColumn('customers', [
            // Google's subject id, not the email: an email can change hands,
            // the subject cannot, and matching on email alone would let someone
            // who takes over an address inherit the account.
            'google_id' => [
                'type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'password_hash',
            ],
            'avatar_url' => [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'google_id',
            ],
            'phone_verified_at' => [
                'type' => 'DATETIME', 'null' => true, 'after' => 'email_verified_at',
            ],
            // Set when a Google sign-up still owes us a phone number. Until it
            // clears, the account exists but cannot check out.
            'profile_completed_at' => [
                'type' => 'DATETIME', 'null' => true, 'after' => 'phone_verified_at',
            ],
        ]);

        $this->db->query('CREATE UNIQUE INDEX idx_customers_google ON customers (google_id)');
        $this->db->query('CREATE INDEX idx_customers_phone ON customers (phone)');

        /*
         * One-time codes, for signing in and for verifying a new email.
         *
         * The code itself is stored HASHED. A leaked database should not hand
         * someone a working set of sign-in codes, and there is never a reason to
         * read one back — only to compare.
         */
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'purpose'    => ['type' => 'ENUM', 'constraint' => ['login', 'verify_email'], 'default' => 'login'],
            'code_hash'  => ['type' => 'VARCHAR', 'constraint' => 255],
            // Everything needed to finish a signup, so a half-made account is
            // never written until the code is confirmed.
            'payload'    => ['type' => 'TEXT', 'null' => true],
            'attempts'   => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 0],
            'expires_at' => ['type' => 'DATETIME'],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['email', 'purpose']);
        $this->forge->addKey('expires_at');
        $this->forge->createTable('auth_codes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('auth_codes', true);
        $this->db->query('DROP INDEX idx_customers_phone ON customers');
        $this->db->query('DROP INDEX idx_customers_google ON customers');
        $this->forge->dropColumn('customers', [
            'google_id', 'avatar_url', 'phone_verified_at', 'profile_completed_at',
        ]);
    }
}
