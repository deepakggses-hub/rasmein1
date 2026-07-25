<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Staff accounts, roles, the audit trail, login throttling records and
 * password-reset tokens.
 *
 * Passwords are stored only as password_hash() output. Reset tokens are
 * stored hashed too — a leaked table must not grant account access.
 */
class CreateAdminTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // ------------------------------------------------------- roles
        $this->forge->addField(
            $this->pk()
            + $this->str('name', 80)
            + $this->str('slug', 60)
            + $this->str('description', 255, true)
            // JSON list of permission keys, e.g. ["products.edit","orders.view"]
            + $this->text('permissions')
            // is_system roles cannot be deleted from the UI.
            + $this->flag('is_system', 0)
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('admin_roles', false, $this->tableAttributes);

        // ------------------------------------------------------- users
        $this->forge->addField(
            $this->pk()
            + $this->ref('role_id')
            + $this->str('name', 120)
            + $this->str('email', 191)
            + $this->str('phone', 20, true)
            + $this->str('password_hash', 255)
            + $this->flag('is_active')
            + $this->flag('must_change_password', 0)
            + $this->flag('two_factor_enabled', 0)
            + $this->str('two_factor_secret', 255, true)
            + $this->datetime('last_login_at')
            + $this->str('last_login_ip', 45, true)
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('role_id');
        $this->forge->addForeignKey('role_id', 'admin_roles', 'id', 'CASCADE', 'RESTRICT', 'fk_admin_users_role');
        $this->forge->createTable('admin_users', false, $this->tableAttributes);

        // --------------------------------------------------- audit log
        $this->forge->addField(
            $this->pk()
            + $this->ref('admin_user_id', true)
            + $this->str('action', 80)          // created | updated | deleted | mode_switched
            + $this->str('module', 60)          // products | orders | settings
            + $this->str('entity_type', 80, true)
            + $this->ref('entity_id', true)
            + $this->str('summary', 255, true)
            + $this->text('old_values')
            + $this->text('new_values')
            + $this->requestFingerprint()
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['module', 'created_at']);
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addKey('admin_user_id');
        $this->forge->addForeignKey('admin_user_id', 'admin_users', 'id', 'CASCADE', 'SET NULL', 'fk_audit_admin');
        $this->forge->createTable('admin_audit_log', false, $this->tableAttributes);

        // ----------------------------------------------- login attempts
        $this->forge->addField(
            $this->pk()
            + $this->enum('user_type', ['admin', 'customer'], 'customer')
            + $this->str('identifier', 191)     // email attempted
            + $this->flag('was_successful', 0)
            + $this->requestFingerprint()
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['identifier', 'created_at']);
        $this->forge->addKey(['ip_address', 'created_at']);
        $this->forge->createTable('auth_login_attempts', false, $this->tableAttributes);

        // --------------------------------------------- password resets
        $this->forge->addField(
            $this->pk()
            + $this->enum('user_type', ['admin', 'customer'], 'customer')
            + $this->ref('user_id')
            + $this->str('email', 191)
            + $this->str('token_hash', 64)      // sha256 of the emailed token
            + $this->datetime('expires_at', false)
            + $this->datetime('used_at')
            + $this->str('requested_ip', 45, true)
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['user_type', 'user_id']);
        $this->forge->createTable('password_resets', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('password_resets', true);
        $this->forge->dropTable('auth_login_attempts', true);
        $this->forge->dropTable('admin_audit_log', true);
        $this->forge->dropTable('admin_users', true);
        $this->forge->dropTable('admin_roles', true);
    }
}
