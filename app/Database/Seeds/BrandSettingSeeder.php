<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Brand identity and the other things a shop must be able to change without a
 * developer: logo, favicon, name, contact details, social links, SEO defaults
 * and legal particulars.
 *
 * These sit in the `settings` table but are LOCKED against the generic settings
 * form, because they have their own screen that knows how to handle uploads.
 *
 * Idempotent — an existing key keeps its value.
 */
class BrandSettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // group, key, value, type, label, description, order
            ['brand', 'brand_logo', '', 'string', 'Logo', 'Shown in the storefront header. A transparent PNG or SVG-like WebP works best. Leave blank to use the wordmark.', 1],
            ['brand', 'brand_logo_light', '', 'string', 'Logo for dark backgrounds', 'Used in the footer and admin bar. Falls back to the main logo.', 2],
            ['brand', 'brand_favicon', '', 'string', 'Favicon', 'The small icon in a browser tab. A square PNG of at least 180px.', 3],
            ['brand', 'brand_og_image', '', 'string', 'Sharing image', 'Shown when a link to the shop is posted on WhatsApp or social media. 1200×630 works everywhere.', 4],

            ['brand', 'meta_title_suffix', '', 'string', 'Title suffix', 'Appended to page titles, e.g. " · Rasmein". Blank uses the shop name.', 10],
            ['brand', 'meta_description', '', 'string', 'Default description', 'Used on pages with nothing more specific. Around 150 characters.', 11],

            ['brand', 'legal_name', '', 'string', 'Registered business name', 'The legal entity, if different from the shop name. Appears on invoices.', 20],
            ['brand', 'legal_gstin', '', 'string', 'GSTIN', 'Shown on invoices where required.', 21],
            ['brand', 'legal_address', '', 'string', 'Registered address', 'Appears in the footer and on invoices.', 22],

            ['social', 'social_whatsapp', '', 'string', 'WhatsApp link', 'A full wa.me link, or leave blank to build one from the WhatsApp number.', 10],
            ['social', 'social_youtube', '', 'string', 'YouTube', 'Full link to the channel.', 11],
            ['social', 'social_pinterest', '', 'string', 'Pinterest', 'Full link to the profile.', 12],
            ['social', 'social_linkedin', '', 'string', 'LinkedIn', 'Full link to the page.', 13],
        ];

        $added = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($rows as [$group, $key, $value, $type, $label, $description, $order]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name' => $key, 'value' => $value, 'value_type' => $type,
                'group_name' => $group, 'label' => $label, 'description' => $description,
                'is_public' => 1, 'is_locked' => 1, 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $added++;
        }

        /*
         * Move any identity key that ended up in the wrong group.
         *
         * SettingsService::set() files an unknown key under 'general', so on an
         * install where this seeder had not run, saving a logo created the row
         * in the wrong place. The resolver reads by key now and copes either
         * way, but leaving rows scattered is confusing for anyone looking at the
         * table, and the brand screen groups by them.
         */
        $moves = [
            'brand' => ['brand_logo', 'brand_logo_light', 'brand_favicon', 'brand_og_image',
                'meta_title_suffix', 'meta_description', 'legal_name', 'legal_gstin', 'legal_address'],
            'store' => ['store_name', 'store_tagline', 'support_email', 'support_phone', 'whatsapp_number'],
            'social' => ['social_instagram', 'social_facebook', 'social_whatsapp',
                'social_youtube', 'social_pinterest', 'social_linkedin'],
        ];

        $moved = 0;

        foreach ($moves as $group => $keys) {
            $this->db->table('settings')
                ->whereIn('key_name', $keys)
                ->where('group_name !=', $group)
                ->set('group_name', $group)
                ->update();

            $moved += $this->db->affectedRows();
        }

        // These are managed by the brand screen, so lock them out of the generic
        // settings form — otherwise the same value is editable in two places,
        // one of which does not understand uploads.
        $this->db->table('settings')
            ->whereIn('group_name', ['store', 'social', 'brand'])
            ->set('is_locked', 1)
            ->update();

        if ($moved > 0) {
            echo "  Brand settings: {$moved} row(s) moved to the correct group.\n";
        }

        echo "  Brand settings: {$added} added (" . count($rows) . " defined).\n";
    }
}
