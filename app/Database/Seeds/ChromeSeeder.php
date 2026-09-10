<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The header and footer settings the Chrome screen manages.
 *
 * Only the ones that did not already exist. Navigation, the search wording and
 * the social links are already seeded elsewhere and stay in their own groups —
 * this adds the footer copy and contact details that had no home at all.
 *
 * Idempotent by key, so an existing value is never overwritten.
 */
class ChromeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            /*
             * Contact details are NOT seeded here.
             *
             * Shop identity already owns `support_email`, `support_phone` and
             * `whatsapp_number`, and those are what BrandService reads. Seeding
             * a `store_`-prefixed twin recreated the duplication that migration
             * 000033 exists to remove — on every fresh install.
             */

            ['design', 'header_notice', '', 'Announcement bar', 10],
            ['design', 'header_notice_link', '', 'Announcement link', 11],

            ['design', 'footer_blurb',
                'Traditions crafted beautifully. Luxury wedding gifts, ritual collections and '
                . 'premium hampers inspired by Indian heritage.',
                'Footer introduction', 20],

            /*
             * "Column | Label | /path", one per line. Lines sharing a column
             * name group under it, in the order written — so a shop reorders a
             * column by moving a line, which is the obvious thing to try.
             */
            ['design', 'footer_columns',
                "Shop | Wedding | /shop\n"
                . "Shop | Festive | /shop\n"
                . "Shop | Corporate | /shop\n"
                . "Company | About | /page/about-us\n"
                . "Company | Contact | /contact\n"
                . "Care | Shipping | /page/shipping-and-delivery\n"
                . "Care | Returns | /page/returns-and-refunds\n"
                . "Legal | Privacy | /page/privacy-policy\n"
                . "Legal | Terms | /page/terms-of-service",
                'Footer link columns', 21],

            ['design', 'footer_note', 'Made with care in India.', 'Footer small print', 22],
        ];

        $added = 0;

        foreach ($rows as [$group, $key, $value, $label, $sort]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'group_name'  => $group,
                'key_name'    => $key,
                'value'       => $value,
                // 'string', not 'text' — value_type is an ENUM without 'text',
                // and an invalid one silently stores an empty string.
                'value_type'  => 'string',
                'label'       => $label,
                'description' => '',
                'is_public'   => 1,
                'is_locked'   => 0,
                'sort_order'  => $sort,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $added++;
        }

        /*
         * Drop the settings cache.
         *
         * A seeder writes straight to the table, so the cached array built on
         * an earlier request still has the old values — the row was there, and
         * get() kept returning nothing. Every seeder that touches `settings`
         * has to do this.
         */
        service('settings')->flush();

        echo '  Header & footer: ' . $added . " setting(s).\n";
    }
}
