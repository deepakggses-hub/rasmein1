<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Editable wording for the sign-in screens, plus the Google credentials.
 *
 * Idempotent: an existing key is left exactly as the shop set it. A seeder that
 * overwrites copy would silently undo someone's edits on the next deploy.
 */
class AuthContentSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [
            ['search_placeholders',
                "What are you looking for?\nWedding hampers, hand packed\nBrass diyas for Diwali\nCorporate gifts, from twenty",
                'string', 'Search box — rotating placeholders',
                'One per line. The search box types through them in turn.', 1],
            ['corporate_banner', 'Corporate gifting — add pieces to an enquiry and we will quote.',
                'string', 'Corporate mode — banner', 'Shown across the top while corporate mode is on.', 2],
            ['corporate_on_message', 'Corporate gifting. Add pieces to an enquiry and we will quote.',
                'string', 'Corporate mode — message when switched on', '', 3],
            ['corporate_off_message', 'Back to ordinary shopping.',
                'string', 'Corporate mode — message when switched off', '', 4],

            ['auth_login_title', 'Welcome back.', 'string', 'Sign in — heading', '', 10],
            ['auth_login_body', 'Enter your email or phone number and we will send you a code.', 'string', 'Sign in — description', '', 11],
            ['auth_register_title', 'Join us.', 'string', 'Create account — heading', '', 12],
            ['auth_register_body', 'We will email you a code to confirm your address.', 'string', 'Create account — description', '', 13],
            ['auth_panel_eyebrow', 'Rasmein', 'string', 'Side panel — small heading', '', 14],
            ['auth_panel_title', 'Gifting that carries a feeling.', 'string', 'Side panel — heading', '', 15],
            ['auth_panel_body', 'Keep your saved pieces, track every order, and reorder a hamper in a tap.', 'string', 'Side panel — description', '', 16],
            ['auth_code_title', 'Check your email.', 'string', 'Code screen — heading', '', 17],
            ['auth_code_body', 'We have sent a six-digit code to', 'string', 'Code screen — line before the address', '', 18],
            ['auth_details_title', 'Just one more thing.', 'string', 'Finish details — heading', '', 19],
            ['auth_details_body', 'We need a number for delivery updates. Change your name here if you would rather we used another.', 'string', 'Finish details — description', '', 20],
        ];

        $added = 0;

        foreach ($rows as [$key, $value, $type, $label, $help, $sort]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name'    => $key,
                'value'       => $value,
                'value_type'  => $type,
                'group_name'  => 'auth',
                'label'       => $label,
                'description' => $help,
                'sort_order'  => $sort,
                'is_public'   => 1,
                'is_locked'   => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $added++;
        }

        /*
         * Google credentials. The secret is stored ENCRYPTED, so the key is
         * seeded empty and filled in through the admin screen — never written
         * here in plain text where it would end up in version control.
         */
        foreach ([
            ['google_auth_client_id', 'Google — client ID', 21],
            ['google_auth_client_secret', 'Google — client secret', 22],
        ] as [$key, $label, $sort]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name'    => $key,
                'value'       => '',
                'value_type'  => 'string',
                'group_name'  => 'auth',
                'label'       => $label,
                'description' => 'From the Google Cloud console, OAuth 2.0 client of type "Web application".',
                'sort_order'  => $sort,
                'is_public'   => 0,
                'is_locked'   => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $added++;
        }

        echo '  Auth content: ' . $added . " setting(s).\n";
    }
}
