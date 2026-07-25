<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Registered customers, their address book, and wishlists.
 *
 * password_hash is nullable: a guest checkout can create a contact record
 * with no credentials, which the customer can later claim by setting a
 * password. Guest orders may also carry customer_id = NULL entirely.
 */
class CreateCustomerTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + $this->str('name', 120)
            + $this->str('email', 191)
            + $this->str('phone', 20, true)
            + $this->str('password_hash', 255, true)
            + $this->datetime('email_verified_at')
            + $this->flag('marketing_opt_in', 0)
            + $this->flag('is_active')
            + $this->datetime('last_login_at')
            + $this->str('notes', 255, true)
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('phone');
        $this->forge->createTable('customers', false, $this->tableAttributes);

        // ---------------------------------------------------- addresses
        $this->forge->addField(
            $this->pk()
            + $this->ref('customer_id')
            + $this->str('label', 40, true)        // "Home", "Office"
            + $this->str('recipient_name', 120)
            + $this->str('phone', 20)
            + $this->str('line1', 191)
            + $this->str('line2', 191, true)
            + $this->str('landmark', 120, true)
            + $this->str('city', 80)
            + $this->str('state', 80)
            + $this->str('postal_code', 12)
            + $this->str('country', 60, false, 'India')
            + $this->flag('is_default_shipping', 0)
            + $this->flag('is_default_billing', 0)
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE', 'fk_addresses_customer');
        $this->forge->createTable('customer_addresses', false, $this->tableAttributes);

        // ----------------------------------------------------- wishlist
        $this->forge->addField(
            $this->pk()
            + $this->ref('customer_id')
            + $this->ref('product_id')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['customer_id', 'product_id']);
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE', 'fk_wishlist_customer');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_wishlist_product');
        $this->forge->createTable('wishlist_items', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('wishlist_items', true);
        $this->forge->dropTable('customer_addresses', true);
        $this->forge->dropTable('customers', true);
    }
}
