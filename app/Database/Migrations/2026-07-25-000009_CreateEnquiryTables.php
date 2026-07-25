<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Lead-tracking fields for an order submitted in Enquire mode.
 *
 * One row per order where journey_mode = 'enquire_now'. The line items,
 * contact details and address live on the order; this table adds the sales
 * pipeline on top — status, owner, follow-up date, quoted value.
 */
class CreateEnquiryTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + $this->ref('order_id')
            + $this->str('enquiry_ref', 32)
            + $this->enum('lead_status', [
                'new', 'contacted', 'quoted', 'won', 'lost', 'spam',
            ], 'new')
            + $this->str('source', 40, false, 'website')
            + $this->str('company', 120, true)
            + $this->enum('preferred_contact', ['email', 'phone', 'whatsapp'], 'phone')
            + $this->text('requirement_note')
            + $this->int('expected_quantity', 0, true)
            + $this->datetime('needed_by')
            + $this->money('estimated_value', true)
            + $this->money('quoted_value', true)
            + $this->ref('assigned_to_admin_id', true)
            + $this->datetime('followup_at')
            + $this->datetime('closed_at')
            + $this->str('lost_reason', 255, true)
            // Set by the honeypot / rate-limit checks; high scores are triaged.
            + $this->int('spam_score')
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('order_id');
        $this->forge->addUniqueKey('enquiry_ref');
        $this->forge->addKey(['lead_status', 'followup_at']);
        $this->forge->addKey('assigned_to_admin_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE', 'fk_enq_order');
        $this->forge->addForeignKey('assigned_to_admin_id', 'admin_users', 'id', 'CASCADE', 'SET NULL', 'fk_enq_admin');
        $this->forge->createTable('enquiries', false, $this->tableAttributes);

        // -------------------------------------------- follow-up notes
        $this->forge->addField(
            $this->pk()
            + $this->ref('enquiry_id')
            + $this->ref('admin_user_id', true)
            + $this->text('note', false)
            + $this->enum('note_type', ['note', 'call', 'email', 'meeting', 'quote'], 'note')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['enquiry_id', 'created_at']);
        $this->forge->addForeignKey('enquiry_id', 'enquiries', 'id', 'CASCADE', 'CASCADE', 'fk_enqn_enquiry');
        $this->forge->addForeignKey('admin_user_id', 'admin_users', 'id', 'CASCADE', 'SET NULL', 'fk_enqn_admin');
        $this->forge->createTable('enquiry_notes', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('enquiry_notes', true);
        $this->forge->dropTable('enquiries', true);
    }
}
