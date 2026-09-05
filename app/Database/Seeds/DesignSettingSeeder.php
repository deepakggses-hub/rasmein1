<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Appearance settings.
 *
 * These are emitted as CSS custom properties into the page head, and every
 * component references the variables rather than hard-coded values. That is
 * what makes the layout adjustable from the admin panel without a rebuild:
 * changing the container width or the card minimum re-flows the whole site,
 * because the grid is expressed in terms of those variables rather than in
 * fixed column counts.
 *
 * Idempotent — an existing key keeps its value.
 */
class DesignSettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // key, value, type, label, description, order
            ['design_container', 'full', 'string', 'Page width', 'Full bleed uses the whole screen. Wide caps it on very large monitors. Boxed is a classic centred column.', 1],
            ['design_max_width', '1800', 'int', 'Maximum width (px)', 'Only used by Wide and Boxed. Ignored on full bleed.', 2],
            ['design_gutter', '3', 'decimal', 'Side spacing (rem)', 'Breathing room at the edges on a large screen. Scales down automatically on smaller ones.', 3],

            // The grid is expressed as a MINIMUM CARD WIDTH, not a column count.
            // The browser then fits as many columns as the screen allows, which
            // is what keeps a full-bleed layout looking right from a phone to an
            // ultrawide without a breakpoint for every size.
            ['design_card_min', '17', 'decimal', 'Narrowest product card (rem)', 'Columns are worked out from this: the wider the screen, the more fit. Smaller value = more columns.', 10],
            ['design_card_min_dense', '13.5', 'decimal', 'Narrowest card, dense view', 'Used by the compact grid toggle on the shop.', 11],
            ['design_card_ratio', '0.82', 'decimal', 'Product image shape', 'Width ÷ height. 1 is square, below 1 is portrait.', 12],

            ['design_radius', '2', 'decimal', 'Corner rounding (px)', 'Cards and inputs. 0 is sharp, 2 is a hairline, 12 is soft.', 20],
            ['design_pill_radius', '999', 'decimal', 'Button rounding (px)', '999 gives the full pill in the design.', 21],

            ['design_color_deep', '#4A0C18', 'string', 'Header and footer', 'The deep band at the top and bottom of every page.', 30],
            ['design_color_primary', '#5E1F3D', 'string', 'Primary buttons', 'Add to cart, place order, and other decisive actions.', 31],
            ['design_color_accent', '#C6A15B', 'string', 'Gold accent', 'Secondary buttons, rules, italic headline accents.', 32],
            ['design_color_surface', '#FBF8F4', 'string', 'Page background', 'The main paper colour behind everything.', 33],
            ['design_color_surface_alt', '#F5EEE6', 'string', 'Alternate band', 'Used by sections that need to sit apart from the page.', 34],

            ['design_nav', "Home | /\nAbout | /page/about\nShop | /shop\nCollections | /collections\nCorporate | /page/corporate-gifting\nContact | /page/contact", 'string', 'Main menu', 'One item per line, written as: Label | /path', 45],

            ['design_sticky_header', '1', 'bool', 'Header follows the page', 'Keeps navigation and the basket reachable while scrolling.', 40],
            ['design_marquee', '1', 'bool', 'Show the promise strip', 'The scrolling band under the homepage hero.', 41],
            ['design_marquee_text', 'Hand-Crafted in India · Slow Luxury · Sustainable Packaging · Bespoke Rituals · Complimentary Gift Wrap', 'string', 'Promise strip wording', 'Separate each phrase with a middle dot.', 42],
        ];

        $added = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($rows as [$key, $value, $type, $label, $description, $order]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name' => $key, 'value' => $value, 'value_type' => $type,
                'group_name' => 'design', 'label' => $label, 'description' => $description,
                'is_public' => 1, 'is_locked' => 1, 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $added++;
        }

        $this->db->table('settings')->where('group_name', 'design')->set('is_locked', 1)->update();

        /*
         * The cached settings array is stale the moment a row is written.
         * SettingsService::all() caches the whole thing, so without this a
         * seeded value sits in the table while get() keeps returning the old
         * one — or nothing at all.
         */
        service('settings')->flush();

        echo "  Design settings: {$added} added (" . count($rows) . " defined).\n";
    }
}
