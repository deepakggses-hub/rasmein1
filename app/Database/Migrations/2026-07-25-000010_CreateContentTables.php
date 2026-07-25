<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Editable content: homepage banners, CMS pages, and the outbound
 * notification log (so "did the customer get the dispatch SMS?" is answerable).
 */
class CreateContentTables extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // ------------------------------------------------------ banners
        $this->forge->addField(
            $this->pk()
            + $this->str('title', 191, true)
            + $this->str('subtitle', 255, true)
            + $this->str('eyebrow', 60, true)
            + $this->str('image', 255, true)
            + $this->str('mobile_image', 255, true)
            + $this->str('alt_text', 191, true)
            + $this->str('link_url', 255, true)
            + $this->str('cta_label', 60, true)
            + $this->enum('position', [
                'home_hero', 'home_strip', 'category_top', 'gift_builder',
            ], 'home_hero')
            + $this->int('sort_order')
            + $this->datetime('starts_at')
            + $this->datetime('ends_at')
            + $this->flag('is_active')
            + $this->stamps()
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['position', 'is_active', 'sort_order']);
        $this->forge->createTable('banners', false, $this->tableAttributes);

        // -------------------------------------------------------- pages
        $this->forge->addField(
            $this->pk()
            + $this->str('title', 191)
            + $this->str('slug', 160)
            + $this->str('excerpt', 255, true)
            + ['content' => ['type' => 'MEDIUMTEXT', 'null' => true]]
            + $this->flag('show_in_footer', 0)
            + $this->int('sort_order')
            + $this->flag('is_active')
            + $this->seoFields()
            + $this->stamps(true, true)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'show_in_footer']);
        $this->forge->createTable('pages', false, $this->tableAttributes);

        // -------------------------------------------- notification log
        $this->forge->addField(
            $this->pk()
            + $this->enum('channel', ['email', 'sms', 'whatsapp'], 'email')
            + $this->str('event', 60)              // order_placed | enquiry_received
            + $this->str('recipient', 191)
            + $this->str('subject', 191, true)
            + $this->str('template', 120, true)
            + $this->str('related_type', 60, true) // order | enquiry
            + $this->ref('related_id', true)
            + $this->enum('status', ['queued', 'sent', 'failed'], 'queued')
            + $this->str('error', 255, true)
            + $this->int('attempts')
            + $this->datetime('sent_at')
            + $this->stamps(false)
        );
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['related_type', 'related_id']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->createTable('notification_log', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_log', true);
        $this->forge->dropTable('pages', true);
        $this->forge->dropTable('banners', true);
    }
}
