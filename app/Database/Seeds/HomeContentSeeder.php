<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Homepage copy and starter testimonials.
 *
 * The copy lives in settings so a shop can rewrite its own philosophy without a
 * developer. Values are seeded here rather than backfilled by a migration,
 * because on a fresh rebuild a migration runs against an empty table — that has
 * bitten this project three times (see CLAUDE.md).
 */
class HomeContentSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['home_philosophy_kicker', 'Our philosophy', 'string', 'Philosophy — small heading', '', 1],
            ['home_philosophy_title', 'Every ritual, a keepsake.', 'string', 'Philosophy — headline', 'The closing two words are set in gold italic automatically.', 2],
            ['home_philosophy_body', "Rasmein was born from the belief that traditions deserve modern reverence — that the smallest ceremony can hold the largest emotions.\n\nEvery hamper is composed by hand, using linen woven in Bhagalpur, brass from Moradabad, and silk from the looms of Varanasi, bound with a single gold seal.", 'string', 'Philosophy — body', 'Leave a blank line between paragraphs.', 3],

            ['home_collections_kicker', 'Featured collections', 'string', 'Collections — small heading', '', 10],
            ['home_collections_title', 'A collection for every occasion.', 'string', 'Collections — headline', '', 11],

            ['home_edit_kicker', 'The edit — best sellers', 'string', 'Best sellers — small heading', '', 20],
            ['home_edit_title', 'Loved by our patrons.', 'string', 'Best sellers — headline', '', 21],

            ['home_occasion_kicker', 'Shop by occasion', 'string', 'Occasions — small heading', '', 30],
            ['home_occasion_title', 'Celebrate every moment worth remembering.', 'string', 'Occasions — headline', '', 31],

            ['home_reviews_kicker', 'From our patrons', 'string', 'Testimonials — small heading', '', 40],
            ['home_reviews_title', 'Words that warm us.', 'string', 'Testimonials — headline', '', 41],

            ['home_clients_title', 'Notable clients', 'string', 'Clients band — title', 'Leave blank to hide the band.', 50],
            ['home_gallery_title', 'Bespoke journey', 'string', 'Gallery — headline', 'Leave blank to hide the gallery.', 60],

            ['home_signup_title', "Enter our world, unhurried.", 'string', 'Sign-up — headline', '', 70],
            ['home_signup_body', 'Ritual notes, seasonal editions and quiet invitations delivered no more than twice a month.', 'string', 'Sign-up — body', '', 71],
        ];

        $added = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($settings as [$key, $value, $type, $label, $description, $order]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name' => $key, 'value' => $value, 'value_type' => $type,
                'group_name' => 'home', 'label' => $label, 'description' => $description,
                // NOT locked: these are plain text with nothing special to
                // handle, so the generic Settings screen can edit them. Locking
                // them without building a screen made them unreachable.
                'is_public' => 1, 'is_locked' => 0, 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        // Repairs installs seeded before this was corrected.
        $this->db->table('settings')->where('group_name', 'home')->set('is_locked', 0)->update();

        $testimonials = [
            ['Every hamper felt like it had been curated for us — the silk, the diya, the note. Our guests were speechless.', 'Anaika & Rohan', 'Wedding · Udaipur', 5],
            ['The Diwali box we sent to our clients was the talk of the season. Rasmein understands what luxury restraint looks like.', 'Meher Kapoor', 'Corporate · Mumbai', 5],
            ['My mother cried when she opened the puja kit. It smelled of temples and childhood. Thank you for that.', 'Vidya Iyer', 'Ritual · Bengaluru', 5],
        ];

        $seeded = 0;

        foreach ($testimonials as $index => [$quote, $author, $role, $rating]) {
            if ($this->db->table('testimonials')->where('author', $author)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('testimonials')->insert([
                'quote' => $quote, 'author' => $author, 'role' => $role, 'rating' => $rating,
                'is_active' => 1, 'sort_order' => $index + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $seeded++;
        }

        echo "  Home content: {$added} setting(s), {$seeded} testimonial(s).\n";
    }
}
