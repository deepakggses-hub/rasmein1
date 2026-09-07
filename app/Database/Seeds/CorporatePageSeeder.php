<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The corporate landing page at /corporate.
 *
 * Idempotent: a page already on this template is left as the shop has it.
 *
 * The product rows seed EMPTY. Which pieces a business should be shown is a
 * merchandising decision, and guessing it would put arbitrary products under a
 * heading the shop did not write — the section simply hides until they choose.
 */
class CorporatePageSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        if ($this->db->table('pages')->where('template', 'corporate')->countAllResults() > 0) {
            echo "  Corporate page: already set up.\n";

            return;
        }

        $data = <<<'JSON'
{"marquee": {"lines": "Bulk pricing from twenty pieces\nGST invoice on every order\nBranded packaging available\nPan-India delivery"}, "occasions": {"eyebrow": "By occasion", "title": "Corporate gifting for every occasion."}, "rows": {"blocks": []}, "split": {"title": "Corporate Gifting For Every Occasion", "image": "", "cards": [{"label": "Birthday Gifts", "icon": "cake", "link": "/shop"}, {"label": "Work Anniversary Gifts", "icon": "calendar", "link": "/shop"}, {"label": "Rewards and Recognition", "icon": "badge", "link": "/shop"}, {"label": "Client Appreciation Gifts", "icon": "cheers", "link": "/shop"}, {"label": "Employee Onboarding", "icon": "user-plus", "link": "/shop"}, {"label": "Thank You Gifts", "icon": "namaste", "link": "/shop"}]}, "invite": {"title": "Tell us what you need.", "body": "Quantities, budget and a date. We will come back with a considered proposal.", "form_title": "Let us craft something special", "whatsapp": ""}}
JSON;

        $this->db->table('pages')->insert([
            'title'            => 'Corporate gifting',
            'slug'             => 'corporate',
            'template'         => 'corporate',
            'excerpt'          => 'Gifting for teams, clients and the occasions in between.',
            'content'          => '',
            'data'             => $data,
            'show_in_footer'   => 0,
            'sort_order'       => 6,
            'is_active'        => 1,
            'meta_title'       => 'Corporate gifting',
            'meta_description' => 'Bulk and branded gifting for teams and clients — '
                . 'onboarding, milestones, festivals and thank-yous.',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        /*
         * Tag the work-shaped occasions for this page.
         *
         * Only ones that exist and are still on the default `both` — a shop that
         * has already decided where an occasion belongs is not overruled.
         */
        foreach (['employee-onboarding', 'work-anniversary', 'client-appreciation'] as $slug) {
            $this->db->table('collections')
                ->where('slug', $slug)->where('audience', 'both')
                ->update(['audience' => 'corporate']);
        }

        echo "  Corporate page: created.\n";
    }
}
