<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Runtime settings an admin can change without a deploy — including the
 * Buy/Enquire master switch, which is read server-side at checkout.
 */
class CreateSettingsTable extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        $this->forge->addField(
            $this->pk()
            + $this->str('group_name', 60, false, 'general')
            + $this->str('key_name', 100)
            + $this->text('value')
            + $this->enum('value_type', ['string', 'int', 'decimal', 'bool', 'json'], 'string')
            + $this->str('label', 191, true)
            + $this->str('description', 255, true)
            // is_public: safe to expose to the storefront (e.g. journey mode).
            + $this->flag('is_public', 0)
            // is_locked: only a super-admin may change it (payment, bank).
            + $this->flag('is_locked', 0)
            + $this->int('sort_order')
            + $this->stamps()
        );

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key_name');
        $this->forge->addKey('group_name');

        $this->forge->createTable('settings', false, $this->tableAttributes);
    }

    public function down(): void
    {
        $this->forge->dropTable('settings', true);
    }
}
