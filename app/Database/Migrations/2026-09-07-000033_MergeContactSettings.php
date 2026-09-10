<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fold the duplicate contact settings back into Shop identity.
 *
 * The Header & footer screen grew its own `store_support_email`,
 * `store_support_phone` and `store_whatsapp` beside Shop identity's
 * `support_email`, `support_phone` and `whatsapp_number`. Two keys for one
 * fact — and since the footer reads the identity ones, editing the pair on the
 * other screen silently changed nothing.
 *
 * Anything typed into the duplicate is carried across FIRST, but only where the
 * identity field is still empty: whatever the shop set on the screen that
 * actually works must win.
 */
class MergeContactSettings extends Migration
{
    private const PAIRS = [
        'store_support_email' => 'support_email',
        'store_support_phone' => 'support_phone',
        'store_whatsapp'      => 'whatsapp_number',
    ];

    public function up(): void
    {
        $db = $this->db;

        foreach (self::PAIRS as $duplicate => $keeper) {
            $from = $db->table('settings')->where('key_name', $duplicate)->get()->getRowArray();

            if ($from === null) {
                continue;
            }

            $value = trim((string) ($from['value'] ?? ''));

            if ($value !== '') {
                $to = $db->table('settings')->where('key_name', $keeper)->get()->getRowArray();

                // Only fill a blank. The identity value is the one in use.
                if ($to === null) {
                    $db->table('settings')->insert([
                        'group_name' => 'store',
                        'key_name'   => $keeper,
                        'value'      => $value,
                        'value_type' => 'string',
                        'label'      => ucfirst(str_replace('_', ' ', $keeper)),
                        'is_public'  => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } elseif (trim((string) $to['value']) === '') {
                    $db->table('settings')->where('key_name', $keeper)->update(['value' => $value]);
                }
            }

            $db->table('settings')->where('key_name', $duplicate)->delete();
        }
    }

    public function down(): void
    {
        // The duplicates come back empty. Restoring their old values would put
        // the shop back in the state this migration existed to end.
        $now = date('Y-m-d H:i:s');

        foreach (array_keys(self::PAIRS) as $duplicate) {
            if ($this->db->table('settings')->where('key_name', $duplicate)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'group_name' => 'store',
                'key_name'   => $duplicate,
                'value'      => '',
                'value_type' => 'string',
                'label'      => ucfirst(str_replace('_', ' ', $duplicate)),
                'is_public'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
