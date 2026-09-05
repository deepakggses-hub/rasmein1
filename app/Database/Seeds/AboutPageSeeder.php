<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The story page, ready to edit.
 *
 * Idempotent: an existing /about page is left exactly as the shop has it. If
 * the old plain-text "About Rasmein" page is still there, it is UPGRADED to the
 * story template rather than duplicated — two about pages is worse than one
 * with the wrong layout.
 */
class AboutPageSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'hero' => [
                'eyebrow' => 'Our story',
                'title'   => 'The house that traditions built.',
                'intro'   => 'Rasmein began with a simple observation — that our most sacred moments '
                    . 'deserve gifts as thoughtful as the ceremonies they honour.',
            ],

            'origin' => [
                'chapter' => 'Chapter one',
                'title'   => 'The origin.',
                'paragraphs' => [
                    ['body' => 'In the winter of 2023, on the eve of a family wedding, our founder searched '
                        . 'for a gift worthy of the occasion — one that carried the weight of ritual without '
                        . 'the noise of commerce. She found none.'],
                    ['body' => 'What began as a hand-composed hamper for a bride became, over three seasons '
                        . 'of quiet work, a small atelier in Jaipur — devoted to gifts that could hold the '
                        . 'emotion of the ceremony they marked.'],
                    ['body' => 'Today, we work with over forty artisans across five states — brass makers in '
                        . 'Moradabad, silk weavers in Varanasi, linen looms in Bhagalpur — to compose gifts '
                        . 'that live somewhere between the sacred and the beautiful.'],
                ],
                'pullquote' => 'Rasmein is Hindi for the customs and traditions we inherit — the small, '
                    . 'sacred practices that outlast us.',
            ],

            // Left empty on purpose: a placeholder photograph on a story page
            // looks like a mistake, and the section hides itself until real
            // ones are uploaded.
            'gallery' => ['image_1' => '', 'alt_1' => '', 'image_2' => '', 'alt_2' => ''],

            'principles' => [
                'eyebrow' => 'Our principles',
                'title'   => 'Three quiet convictions.',
                'items'   => [
                    ['title' => 'Slow, by design.',
                        'body' => 'Every hamper is composed by hand. Nothing is machined, rushed, or sourced '
                            . 'anonymously. If a piece needs three weeks, it takes three weeks.'],
                    ['title' => 'Rooted in craft.',
                        'body' => 'We collaborate with a small circle of Indian artisans — brass, silk, linen, '
                            . 'brass, wax — and pay directly for what they make, at prices set by them.'],
                    ['title' => 'Wrapped with intention.',
                        'body' => 'Our packaging is compostable linen, hand-lettered cards and a single gold '
                            . 'wax seal. No plastic, no filler, no excess — just the gift and its silence.'],
                ],
            ],

            'history' => [
                'eyebrow' => 'The atelier',
                'title'   => 'A small history.',
                'items'   => [
                    ['year' => '2023', 'title' => 'The first hamper.',
                        'body' => 'Composed by hand for a family wedding in Udaipur — silk, brass, and a '
                            . 'hand-lettered blessing.'],
                    ['year' => '2024', 'title' => 'The Jaipur atelier opens.',
                        'body' => 'A studio of twelve artisans, in a converted haveli, dedicated to gifts '
                            . 'made unhurriedly.'],
                    ['year' => '2024', 'title' => 'The Ritual Collection.',
                        'body' => 'Our first full series — twenty-eight pieces composed for the small '
                            . 'ceremonies of daily life.'],
                    ['year' => '2025', 'title' => 'Four states, forty hands.',
                        'body' => 'Our circle of artisans grows to include Moradabad brass, Varanasi silk, '
                            . 'Bhagalpur linen, and Kerala wax work.'],
                ],
            ],

            'founder' => [
                'eyebrow' => 'A note from the founder',
                'quote'   => 'We make gifts we would want to give — quietly, thoughtfully, and with the '
                    . 'reverence a ritual deserves.',
                'name'    => 'Aanya',
                'role'    => 'Founder · Rasmein',
            ],

            'invite' => [
                'title'      => 'Enter our world, unhurried.',
                'body'       => 'Ritual notes, seasonal editions and quiet invitations delivered no more '
                    . 'than twice a month.',
                'form_title' => 'Let us craft something special',
                'whatsapp'   => '919876543210',
            ],
        ];

        $existing = $this->db->table('pages')->where('slug', 'about-us')->get()->getRowArray()
            ?? $this->db->table('pages')->where('slug', 'about')->get()->getRowArray();

        if ($existing !== null) {
            // Already on the story template: the shop has edited it, leave it be.
            if (($existing['template'] ?? '') === 'about') {
                echo "  About page: already a story page.\n";

                return;
            }

            $this->db->table('pages')->where('id', $existing['id'])->update([
                'template'   => 'about',
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            echo "  About page: upgraded to the story template.\n";

            return;
        }

        $this->db->table('pages')->insert([
            'title'            => 'About',
            'slug'             => 'about-us',
            'template'         => 'about',
            'excerpt'          => 'The house that traditions built.',
            'content'          => '',
            'data'             => json_encode($data, JSON_UNESCAPED_UNICODE),
            'show_in_footer'   => 1,
            'sort_order'       => 10,
            'is_active'        => 1,
            'meta_title'       => 'About Rasmein',
            'meta_description' => 'Rasmein began with a simple observation — that our most sacred moments '
                . 'deserve gifts as thoughtful as the ceremonies they honour.',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        echo "  About page: created.\n";
    }
}
