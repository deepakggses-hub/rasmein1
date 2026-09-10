<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The shop's occasions, on both sides of the house.
 *
 * Nine for the ordinary shop and eight for the corporate page. They are the
 * same kind of row — `type = 'occasion'` — separated by `audience`, so the
 * storefront asks one question ("what belongs on this page") rather than
 * knowing about two lists.
 *
 * Idempotent by slug: an occasion that already exists is left exactly as the
 * shop has it, including its audience. Someone who has deliberately moved
 * Festivals onto the corporate page should not find it moved back by a deploy.
 *
 * Images are left empty. A placeholder on an occasion tile reads as a fault
 * rather than an absence, and the tile is legible from its name alone.
 */
class OccasionSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [name, slug, description] — order here is the order on the page.
        $retail = [
            ['Birthdays', 'birthdays',
                'For the year turning over — something to open before the cake.'],
            ['Weddings', 'weddings',
                'Trousseau, welcome boxes and the pieces a new household begins with.'],
            ['Anniversaries', 'anniversaries',
                'A year, ten years, fifty. Marked properly.'],
            ['Baby Shower', 'baby-shower',
                'Soft, small and made to be kept long after it is outgrown.'],
            ['Housewarming', 'housewarming',
                'For the first meal cooked and the first lamp lit in a new home.'],
            ['Achievements', 'achievements',
                'A degree, a promotion, a first job — the milestones worth a hamper.'],
            ['Festivals', 'festivals',
                'Diwali, Rakhi, Holi and the smaller days between them.'],
            ['Religious & Spiritual', 'religious-and-spiritual',
                'Pooja thalis, diyas and pieces for the prayer room.'],
            ['Corporate', 'corporate-gifting',
                'Gifting for teams and clients, at any scale.'],
        ];

        $corporate = [
            ['Employee Welcome', 'employee-welcome',
                'The first day, marked. Desk pieces and a note that reads as meant.'],
            ['Employee Appreciation', 'employee-appreciation',
                'For work done well, when a message alone is not enough.'],
            ['Client Gifting', 'client-gifting',
                'Considered pieces for the relationships the business runs on.'],
            ['Business Partner Gifts', 'business-partner-gifts',
                'For the people you build with — substantial, and quietly branded.'],
            ['Festive Corporate Gifting', 'festive-corporate-gifting',
                'Diwali and New Year hampers, at volume, delivered on a date.'],
            ['Employee Milestones', 'employee-milestones',
                'One year, five, ten. Gifts that scale with the service.'],
            ['Conference & Event Gifting', 'conference-and-event-gifting',
                'Delegate kits and speaker gifts, packed and counted to a brief.'],
            ['VIP & Premium Gifting', 'vip-and-premium-gifting',
                'The top of the range, for the handful of people who warrant it.'],
        ];

        $added = 0;
        $kept  = 0;
        $sort  = 0;

        foreach ([['retail', $retail], ['corporate', $corporate]] as [$audience, $rows]) {
            foreach ($rows as [$name, $slug, $description]) {
                $sort += 10;

                if ($this->db->table('collections')->where('slug', $slug)->countAllResults() > 0) {
                    $kept++;

                    continue;
                }

                $this->db->table('collections')->insert([
                    'type'        => 'occasion',
                    'audience'    => $audience,
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'image'       => '',
                    'alt_text'    => '',
                    'is_featured' => 0,
                    'sort_order'  => $sort,
                    'is_active'   => 1,
                    /*
                     * No dates. These are standing occasions, not a seasonal
                     * window — Birthdays do not expire, and an end date would
                     * 404 the page the day it passed.
                     */
                    'starts_at'        => null,
                    'ends_at'          => null,
                    'meta_title'       => $name . ' gifts',
                    'meta_description' => $description,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);

                $added++;
            }
        }

        echo '  Occasions: ' . $added . ' added, ' . $kept . " already there.\n";
    }
}
