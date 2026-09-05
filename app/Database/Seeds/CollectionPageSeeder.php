<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Landing-page copy for the occasions and collections.
 *
 * Idempotent, and CONSERVATIVE: a row that already has `data` is left exactly
 * as the shop has it. Overwriting edited copy on every deploy is the kind of
 * thing that makes people stop running seeders.
 *
 * Images are seeded empty. A placeholder photograph on a hero band looks like a
 * fault rather than an absence, and every section hides itself until there is
 * something real to show.
 */
class CollectionPageSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $pages = [
            'diwali-2026' => [
                'hero' => [
                    'eyebrow' => 'Collection III · Festive',
                    'title'   => 'A season, wrapped in linen.',
                    'intro'   => 'From the first Diwali diya to the last Karwa Chauth thali — '
                        . 'the year, one festival at a time.',
                    'aside'   => 'A festival is the year remembering itself.',
                ],
                'ethos' => [
                    'eyebrow' => 'The ethos',
                    'title'   => 'Every festival, a small edition.',
                    'paragraphs' => [
                        ['body' => 'Each festival gets its own limited edition — composed once, '
                            . 'for one season, then quietly retired.'],
                        ['body' => 'Diwali arrives in velvet, Rakhi in silk thread, Holi in coloured '
                            . 'linen. Every edition carries its own hand-pressed seal.'],
                        ['body' => 'When it is gone, it is gone. That, too, is part of the ritual.'],
                    ],
                ],
                'tiles' => [
                    'eyebrow' => 'By festival',
                    'title'   => 'Every celebration, considered.',
                    'items'   => [
                        ['label' => 'Diwali', 'image' => '', 'link' => '/shop'],
                        ['label' => 'Rakhi', 'image' => '', 'link' => '/shop'],
                        ['label' => 'Karwa Chauth', 'image' => '', 'link' => '/shop'],
                        ['label' => 'Holi & Navratri', 'image' => '', 'link' => '/shop'],
                    ],
                ],
                'edit' => [
                    'eyebrow'    => 'The pieces',
                    'title'      => 'The Festive Edit.',
                    'link_label' => 'View all',
                ],
                'feature' => [
                    'eyebrow'   => 'This season',
                    'title'     => 'Diwali — the Velvet Edit.',
                    'body'      => 'Our Diwali edition arrives in deep burgundy velvet, lined with silk. '
                        . 'Twelve hand-cast diyas, eight traditional mithai, and a single gold wax seal.',
                    'image'     => '',
                    'cta_label' => 'Read the journal',
                    'cta_link'  => '/shop',
                ],
                'invite' => [
                    'title'      => 'Enter our world, unhurried.',
                    'body'       => 'Ritual notes, seasonal editions and quiet invitations delivered '
                        . 'no more than twice a month.',
                    'form_title' => 'Let us craft something special',
                    'whatsapp'   => '',
                ],
            ],

            'for-a-new-home' => [
                'hero' => [
                    'eyebrow' => 'Collection · Housewarming',
                    'title'   => 'A house, made a home.',
                    'intro'   => 'Pieces for the first meal cooked, the first guest welcomed, '
                        . 'the first lamp lit.',
                    'aside'   => 'Every home begins with someone bringing something.',
                ],
                'ethos' => [
                    'eyebrow' => 'The ethos',
                    'title'   => 'Useful, and meant to last.',
                    'paragraphs' => [
                        ['body' => 'A housewarming gift is used, not displayed. Everything here is '
                            . 'made to be picked up daily and to survive it.'],
                        ['body' => 'Brass that takes a patina, linen that softens, silver that can '
                            . 'be polished back. Nothing that looks worse for being loved.'],
                    ],
                ],
                'edit' => ['eyebrow' => 'The pieces', 'title' => 'For a new home.', 'link_label' => 'View all'],
                'invite' => [
                    'title'      => 'Gifting for many homes?',
                    'body'       => 'Tell us how many and by when, and we will come back with a plan.',
                    'form_title' => 'Let us craft something special',
                    'whatsapp'   => '',
                ],
            ],

            'the-tea-drinker' => [
                'hero' => [
                    'eyebrow' => 'Collection · The Tea Drinker',
                    'title'   => 'The hour, kept warm.',
                    'intro'   => 'For the person whose day is measured in cups.',
                    'aside'   => 'Tea is the excuse. Sitting down is the point.',
                ],
                'ethos' => [
                    'eyebrow' => 'The ethos',
                    'title'   => 'A ritual, not a beverage.',
                    'paragraphs' => [
                        ['body' => 'Chai is made the same way in a million kitchens and never quite '
                            . 'the same twice. These pieces are for that.'],
                    ],
                ],
                'edit' => ['eyebrow' => 'The pieces', 'title' => 'The Tea Drinker.', 'link_label' => 'View all'],
                'invite' => [
                    'title'      => 'Enter our world, unhurried.',
                    'body'       => 'Ritual notes and seasonal editions, no more than twice a month.',
                    'form_title' => 'Let us craft something special',
                    'whatsapp'   => '',
                ],
            ],
        ];

        $written = 0;
        $kept    = 0;

        foreach ($pages as $slug => $data) {
            $row = $this->db->table('collections')->where('slug', $slug)->get()->getRowArray();

            if ($row === null) {
                continue;
            }

            // Already written by the shop: leave it alone.
            if (! empty($row['data']) && $row['data'] !== 'null' && $row['data'] !== '[]') {
                $kept++;

                continue;
            }

            $this->db->table('collections')->where('id', $row['id'])->update([
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            $written++;
        }

        echo '  Collection pages: ' . $written . ' written, ' . $kept . " left as edited.\n";
    }
}
