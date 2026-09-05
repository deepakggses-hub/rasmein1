<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Wishlists for people who have not signed in.
 *
 * `wishlist_items.customer_id` was NOT NULL, so a guest could not save anything
 * — the heart on a product card simply did nothing until you had an account.
 * A visitor should be able to collect things first and decide to register
 * later, which is also when they are most likely to.
 *
 * The row is keyed by EITHER a customer or a visitor token, never both. The
 * token lives in a long-lived httpOnly cookie, so it survives closing the
 * browser but cannot be read by scripts on the page.
 */
class AddGuestBaskets extends Migration
{
    public function up(): void
    {
        /*
         * The column cannot be made nullable while a foreign key references it,
         * so the key comes off and goes straight back on. It is still enforced
         * afterwards — a NULL customer_id is simply exempt, which is exactly
         * what "this row belongs to a guest" means.
         */
        $this->db->query('ALTER TABLE wishlist_items DROP FOREIGN KEY fk_wishlist_customer');
        $this->db->query('ALTER TABLE wishlist_items MODIFY customer_id INT(11) UNSIGNED NULL');
        $this->db->query(
            'ALTER TABLE wishlist_items ADD CONSTRAINT fk_wishlist_customer '
            . 'FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $this->forge->addColumn('wishlist_items', [
            'visitor_token' => [
                'type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'customer_id',
            ],
        ]);

        /*
         * Unique per visitor as well as per customer, so adding the same product
         * twice is a no-op at the DATABASE rather than something the application
         * has to remember to check.
         */
        $this->db->query('CREATE UNIQUE INDEX idx_wishlist_visitor ON wishlist_items (visitor_token, product_id)');

        // Carts already carry session_id; this is the same idea under a name
        // that says what it actually is.
        $this->forge->addColumn('carts', [
            'visitor_token' => [
                'type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'session_id',
            ],
        ]);

        $this->db->query('CREATE INDEX idx_carts_visitor ON carts (visitor_token, status)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_carts_visitor ON carts');
        $this->forge->dropColumn('carts', 'visitor_token');
        $this->db->query('DROP INDEX idx_wishlist_visitor ON wishlist_items');
        $this->forge->dropColumn('wishlist_items', 'visitor_token');
    }
}
