<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The contact page, ready to edit.
 *
 * Idempotent: an existing /contact is left exactly as the shop has it. A seeder
 * that overwrote a page would undo someone's work on the next deploy — and this
 * one runs in the normal chain, so that would happen silently.
 */
class ContactPageSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('pages')->where('slug', 'contact')->countAllResults() > 0) {
            echo "  Contact page: already there.\n";

            return;
        }

        $now = date('Y-m-d H:i:s');

        $data = [
            'hero' => [
                'eyebrow' => 'Get in touch',
                'title'   => 'Write to the atelier.',
                'intro'   => 'For orders, bespoke enquiries, corporate gifting, or just to say hello. '
                    . 'We reply within one business day.',
            ],

            'channels' => [
                'items' => [
                    [
                        'icon'  => 'mail',
                        'title' => 'Email',
                        'note'  => 'General enquiries & orders',
                        'value' => 'hello@rasmein.com',
                        'link'  => 'mailto:hello@rasmein.com',
                    ],
                    [
                        'icon'  => 'phone',
                        'title' => 'Phone',
                        'note'  => 'Mon–Sat · 10am to 7pm',
                        'value' => '+91 98765 43210',
                        'link'  => 'tel:+919876543210',
                    ],
                    [
                        'icon'  => 'whatsapp',
                        'title' => 'WhatsApp',
                        'note'  => 'Fastest response',
                        'value' => 'Message on WhatsApp',
                        'link'  => 'https://wa.me/919876543210',
                    ],
                    [
                        'icon'  => 'pin',
                        'title' => 'Visit',
                        'note'  => 'Jaipur atelier · By appointment',
                        'value' => 'Book a visit',
                        'link'  => '/contact',
                    ],
                ],
            ],

            'invite' => [
                'eyebrow'       => 'Say hello',
                'title'         => 'Let us compose something for you.',
                'body'          => 'Whether you are planning a wedding, a corporate gift for 200, or a '
                    . 'single bespoke hamper — write to us and we will get back with a considered proposal.',
                'address_label' => 'Our atelier',
                'address'       => "The Haveli No. 14\nNahargarh Road, Amer\nJaipur, Rajasthan 302028\nIndia",
            ],

            'band' => [
                // Left empty deliberately: a placeholder photograph on a
                // contact page looks like a mistake, and the section hides
                // itself until a real one is uploaded.
                'image'   => '',
                'caption' => 'The Rasmein Atelier',
                'note'    => 'Amer · Jaipur',
            ],

            'faq' => [
                'eyebrow' => 'Frequently asked',
                'title'   => 'Questions we often hear.',
                'items'   => [
                    [
                        'q' => 'How long does an order take to arrive?',
                        'a' => 'Standard orders ship within 3 business days across India, with delivery in '
                            . '4–7 days. Bespoke pieces take 3–6 weeks depending on complexity.',
                    ],
                    [
                        'q' => 'Do you ship internationally?',
                        'a' => 'Yes, to most countries. Write to us with your destination and we will confirm '
                            . 'timings and cost before you order.',
                    ],
                    [
                        'q' => 'Can I customise a hamper?',
                        'a' => 'Every hamper can be composed to your brief — the contents, the linen, the '
                            . 'card and the seal. Start with the gift box builder or write to us.',
                    ],
                    [
                        'q' => 'Do you accept corporate orders?',
                        'a' => 'We do, from twenty pieces upwards, with branded cards and a single delivery '
                            . 'schedule. Tell us the occasion and the number and we will propose options.',
                    ],
                    [
                        'q' => 'What is your return policy?',
                        'a' => 'Unopened standard pieces can be returned within 7 days of delivery. Bespoke '
                            . 'and personalised work cannot be returned, for reasons we hope are obvious.',
                    ],
                ],
            ],
        ];

        $this->db->table('pages')->insert([
            'title'            => 'Contact',
            'slug'             => 'contact',
            'template'         => 'contact',
            'excerpt'          => 'Write to the atelier.',
            // The standard template's body is unused here, but the column is
            // NOT NULL in some installs.
            'content'          => '',
            'data'             => json_encode($data, JSON_UNESCAPED_UNICODE),
            'show_in_footer'   => 1,
            'sort_order'       => 30,
            'is_active'        => 1,
            'meta_title'       => 'Contact Rasmein',
            'meta_description' => 'Write to the atelier for orders, bespoke enquiries and corporate gifting.',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        echo "  Contact page: created.\n";
    }
}
