<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * In-app notifications for staff, and editable email templates.
 *
 * DESIGN NOTE — why one notification row per admin rather than one broadcast
 * row plus a "who has read it" table:
 *
 * Read state is the thing that gets queried on every page load (for the unread
 * badge), and a fan-out table makes that a join with a NOT EXISTS. With a row
 * per recipient it is a single indexed count. A gifting business has a handful
 * of staff, not thousands, so the duplication costs nothing and the read path
 * stays trivial. It also lets a notification be targeted — only the people who
 * hold the permission to act on it get one.
 */
class CreateNotificationTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // ------------------------------------------- staff notifications
        $this->forge->addField(
            $this->pk()
            + $this->ref('admin_user_id')
            + $this->str('event', 60)              // order_placed | enquiry_received | low_stock
            + $this->str('title', 191)
            + $this->str('body', 500, true)
            + $this->str('link_url', 255, true)
            + $this->enum('severity', ['info', 'success', 'warning', 'urgent'], 'info')
            + $this->str('entity_type', 60, true)
            + $this->ref('entity_id', true)
            // Lets a repeated condition (same product still low) avoid piling up.
            + $this->str('dedupe_key', 191, true)
            + $this->flag('is_read', 0)
            + $this->datetime('read_at')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['admin_user_id', 'is_read', 'id']);
        $this->forge->addKey(['dedupe_key', 'created_at']);
        $this->forge->addForeignKey('admin_user_id', 'admin_users', 'id', 'CASCADE', 'CASCADE', 'fk_notif_admin');
        $this->forge->createTable('admin_notifications', false, $this->tableAttributes);

        // ---------------------------------------------- email templates
        $this->forge->addField(
            $this->pk()
            // Stable machine key the code refers to. Never edited from the UI.
            + $this->str('template_key', 80)
            + $this->str('name', 120)
            + $this->str('description', 255, true)
            + $this->enum('audience', ['customer', 'admin'], 'customer')
            + $this->str('subject', 255)
            + ['body_html' => ['type' => 'MEDIUMTEXT', 'null' => true]]
            // Placeholders this template may use, as a JSON map of
            // token => human description. Drives the editor's helper list and
            // is the allowlist the renderer works from.
            + $this->text('placeholders')
            // is_system templates cannot be deleted — the code sends them.
            + $this->flag('is_system', 1)
            + $this->flag('is_active')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('template_key');
        $this->forge->addKey(['audience', 'is_active']);
        $this->forge->createTable('email_templates', false, $this->tableAttributes);

        // -------------------------- extend the existing notification log
        // The log already records what was queued; it now also needs to carry
        // the rendered message and the retry bookkeeping the sender uses.
        $this->forge->addColumn('notification_log', [
            'template_key' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'template'],
            'body_html'    => ['type' => 'MEDIUMTEXT', 'null' => true, 'after' => 'template_key'],
            'payload'      => ['type' => 'TEXT', 'null' => true, 'after' => 'body_html'],
            'next_attempt_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'attempts'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notification_log', ['template_key', 'body_html', 'payload', 'next_attempt_at']);
        $this->forge->dropTable('email_templates', true);
        $this->forge->dropTable('admin_notifications', true);
    }
}
