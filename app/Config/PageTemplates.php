<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The layouts a page can use, and the fields each one needs.
 *
 * ONE definition drives three things: the picker in the admin, the fields that
 * form renders, and what the storefront reads. Keeping them in separate places
 * is how a field ends up saveable but never displayed — or displayed but never
 * editable, which is worse because nothing says why.
 *
 * Field types: text, textarea, rich, image, list. A `list` is a repeatable
 * group; its `fields` describe one row.
 */
class PageTemplates extends BaseConfig
{
    /** @var array<string, array<string, mixed>> */
    public array $templates = [
        'standard' => [
            'label'       => 'Standard page',
            'description' => 'A title and a block of text. Right for policies and long copy.',
            'view'        => 'storefront/page',
            'sections'    => [],
        ],

        'corporate' => [
            'route'       => 'corporate',
            'label'       => 'Corporate page',
            'description' => 'The landing page at /corporate — banner, marquee, work occasions, '
                . 'product rows, a split feature and an enquiry form.',
            'view'        => 'storefront/pages/corporate',
            'sections'    => [
                'marquee' => [
                    'label'  => 'Message strip',
                    'help'   => 'The band under the banner. One phrase per line.',
                    'fields' => [
                        'lines' => ['type' => 'lines', 'label' => 'Phrases'],
                    ],
                ],

                'occasions' => [
                    'label'  => 'Work occasions',
                    'help'   => 'Shows every occasion tagged for the corporate page. '
                        . 'Tag them under Catalogue -> Occasions.',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'By occasion'],
                        'title'   => ['type' => 'text', 'label' => 'Heading',
                            'default' => 'Corporate gifting for every occasion.'],
                    ],
                ],

                'rows' => [
                    'label'  => 'Product rows',
                    'help'   => 'Each row is a heading and a set of pieces. Add as many as you need.',
                    'fields' => [
                        'blocks' => [
                            'type'   => 'list',
                            'label'  => 'Rows',
                            'max'    => 6,
                            'fields' => [
                                'title'    => ['type' => 'text', 'label' => 'Heading'],
                                'link'     => ['type' => 'text', 'label' => 'View-all links to'],
                                'products' => ['type' => 'products', 'label' => 'Pieces'],
                            ],
                        ],
                    ],
                ],

                'split' => [
                    'label'  => 'Occasion panel',
                    'help'   => 'The maroon panel with linked cards beside a photograph. '
                        . 'Leave the heading blank to hide it.',
                    'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Heading',
                            'default' => 'Corporate Gifting For Every Occasion'],
                        'image' => ['type' => 'image', 'label' => 'Photograph, right side'],
                        'cards' => [
                            'type'   => 'list',
                            'label'  => 'Cards',
                            'max'    => 8,
                            'fields' => [
                                'label' => ['type' => 'text', 'label' => 'Label'],
                                'icon'  => ['type' => 'text', 'label' => 'Icon',
                                    'help' => 'cake, calendar, badge, cheers, user-plus or namaste.'],
                                'link'  => ['type' => 'text', 'label' => 'Links to'],
                            ],
                        ],
                    ],
                ],

                'invite' => [
                    'label'  => 'Enquiry form',
                    'help'   => 'Leave the heading blank to hide this section.',
                    'fields' => [
                        'title'      => ['type' => 'text', 'label' => 'Heading',
                            'default' => 'Tell us what you need.'],
                        'body'       => ['type' => 'textarea', 'label' => 'Paragraph'],
                        'form_title' => ['type' => 'text', 'label' => 'Form heading',
                            'default' => 'Let us craft something special'],
                        'whatsapp'   => ['type' => 'text', 'label' => 'WhatsApp number'],
                    ],
                ],
            ],
        ],

        'collections' => [
            /*
             * This template needs data the generic page route cannot supply —
             * the occasion tiles and their products come from a dedicated
             * action. `route` sends /page/{slug} there instead of rendering a
             * half-empty copy at a second address.
             */
            'route'       => 'collection',
            'label'       => 'Collections page',
            'description' => 'The landing page at /collection — hero, ethos, festival tiles, '
                . 'a product edit, a season feature and an enquiry form.',
            'view'        => 'storefront/pages/collections',
            'sections'    => [
        'hero' => [
            'label'  => 'Hero band',
            'fields' => [
                'eyebrow'  => ['type' => 'text', 'label' => 'Small heading', 'help' => 'e.g. Collection III · Festive'],
                'title'    => ['type' => 'text', 'label' => 'Heading',
                    'help' => 'The second word from the end is set in gold italic.'],
                'intro'    => ['type' => 'textarea', 'label' => 'Introduction'],
                'aside'    => ['type' => 'text', 'label' => 'Quiet line, right side'],
                'image'    => ['type' => 'image', 'label' => 'Background photograph',
                    'help' => 'Sits behind the heading, under a dark scrim.'],
            ],
        ],

        'ethos' => [
            'label'  => 'The ethos',
            'fields' => [
                'eyebrow'    => ['type' => 'text', 'label' => 'Small heading', 'default' => 'The ethos'],
                'title'      => ['type' => 'text', 'label' => 'Heading'],
                'paragraphs' => [
                    'type'   => 'list',
                    'label'  => 'Paragraphs',
                    'max'    => 6,
                    'fields' => ['body' => ['type' => 'textarea', 'label' => 'Paragraph']],
                ],
            ],
        ],

        'tiles' => [
            'label'  => 'By festival',
            'help'   => 'A row of image tiles. Each links wherever you point it.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'By festival'],
                'title'   => ['type' => 'text', 'label' => 'Heading'],
                'occasions' => [
                    'type'  => 'occasions',
                    'label' => 'Which occasions',
                    'help'  => 'Tick the ones to show, in the order they are listed. '
                        . 'Tick none and every live occasion appears.',
                ],
            ],
        ],

        'edit' => [
            'label'  => 'The pieces',
            'fields' => [
                'eyebrow'  => ['type' => 'text', 'label' => 'Small heading', 'default' => 'The pieces'],
                'title'    => ['type' => 'text', 'label' => 'Heading'],
                'link_label' => ['type' => 'text', 'label' => 'Link text', 'default' => 'View all'],
                'source'     => [
                    'type'  => 'occasions',
                    'label' => 'Pieces from which occasions',
                    'help'  => 'Tick none and the pieces come from whatever is ticked above, '
                        . 'or from every occasion if that is empty too.',
                ],
            ],
        ],

        'feature' => [
            'label'  => 'Season feature',
            'help'   => 'The deep band. Leave the heading blank to hide it.',
            'fields' => [
                'eyebrow'   => ['type' => 'text', 'label' => 'Small heading', 'default' => 'This season'],
                'title'     => ['type' => 'text', 'label' => 'Heading'],
                'body'      => ['type' => 'textarea', 'label' => 'Paragraph'],
                'image'     => ['type' => 'image', 'label' => 'Image'],
                'cta_label' => ['type' => 'text', 'label' => 'Button text'],
                'cta_link'  => ['type' => 'text', 'label' => 'Button links to'],
            ],
        ],

        'invite' => [
            'label'  => 'Enquiry form',
            'help'   => 'Leave the heading blank to hide this section.',
            'fields' => [
                'title'      => ['type' => 'text', 'label' => 'Heading'],
                'body'       => ['type' => 'textarea', 'label' => 'Paragraph'],
                'form_title' => ['type' => 'text', 'label' => 'Form heading', 'default' => 'Let us craft something special'],
                'whatsapp'   => ['type' => 'text', 'label' => 'WhatsApp number'],
            ],
        ],
            ],
        ],

        'about' => [
            'label'       => 'About / story page',
            'description' => 'A long-form story: chapters, principles, a timeline and a founder note.',
            'view'        => 'storefront/pages/about',
            'sections'    => [
                'hero' => [
                    'label'  => 'Opening',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'Our story'],
                        'title'   => ['type' => 'text', 'label' => 'Heading',
                            'default' => 'The house that traditions built.',
                            'help' => 'The SECOND word from the end is set in gold italic.'],
                        'intro'   => ['type' => 'textarea', 'label' => 'Introduction'],
                    ],
                ],

                'origin' => [
                    'label'  => 'The story',
                    'help'   => 'Each paragraph opens with a drop capital, as in the design.',
                    'fields' => [
                        'chapter' => ['type' => 'text', 'label' => 'Chapter label', 'default' => 'Chapter one'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'The origin.'],
                        'paragraphs' => [
                            'type'   => 'list',
                            'label'  => 'Paragraphs',
                            'max'    => 8,
                            'fields' => [
                                'body' => ['type' => 'textarea', 'label' => 'Paragraph'],
                            ],
                        ],
                        'pullquote' => ['type' => 'textarea', 'label' => 'Pull quote',
                            'help' => 'Set apart with a gold rule, after the second paragraph.'],
                    ],
                ],

                'gallery' => [
                    'label'  => 'Two photographs',
                    'fields' => [
                        'image_1'  => ['type' => 'image', 'label' => 'Left image'],
                        'alt_1'    => ['type' => 'text', 'label' => 'Left image, described'],
                        'image_2'  => ['type' => 'image', 'label' => 'Right image'],
                        'alt_2'    => ['type' => 'text', 'label' => 'Right image, described'],
                    ],
                ],

                'principles' => [
                    'label'  => 'Principles',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'Our principles'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'Three quiet convictions.'],
                        'items'   => [
                            'type'   => 'list',
                            'label'  => 'Convictions',
                            'max'    => 6,
                            'fields' => [
                                'title' => ['type' => 'text', 'label' => 'Heading'],
                                'body'  => ['type' => 'textarea', 'label' => 'Paragraph'],
                            ],
                        ],
                    ],
                ],

                'history' => [
                    'label'  => 'Timeline',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'The atelier'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'A small history.'],
                        'items'   => [
                            'type'   => 'list',
                            'label'  => 'Moments',
                            'max'    => 12,
                            'fields' => [
                                'year'  => ['type' => 'text', 'label' => 'Year'],
                                'title' => ['type' => 'text', 'label' => 'What happened'],
                                'body'  => ['type' => 'textarea', 'label' => 'A line or two'],
                            ],
                        ],
                    ],
                ],

                'founder' => [
                    'label'  => 'Founder note',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'A note from the founder'],
                        'quote'   => ['type' => 'textarea', 'label' => 'The quote'],
                        'name'    => ['type' => 'text', 'label' => 'Name'],
                        'role'    => ['type' => 'text', 'label' => 'Role'],
                    ],
                ],

                'invite' => [
                    'label'  => 'Enquiry form',
                    'help'   => 'Leave the heading blank to hide this whole section.',
                    'fields' => [
                        'title'      => ['type' => 'text', 'label' => 'Heading', 'default' => 'Enter our world, unhurried.'],
                        'body'       => ['type' => 'textarea', 'label' => 'Paragraph'],
                        'form_title' => ['type' => 'text', 'label' => 'Form heading', 'default' => 'Let us craft something special'],
                        'whatsapp'   => ['type' => 'text', 'label' => 'WhatsApp number',
                            'help' => 'Digits only, with country code. Blank hides the WhatsApp button.'],
                    ],
                ],
            ],
        ],

        'contact' => [
            'label'       => 'Contact page',
            'description' => 'Ways to reach you, an address, a photograph and a list of questions.',
            'view'        => 'storefront/pages/contact',
            'sections'    => [
                'hero' => [
                    'label'  => 'Opening',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'Get in touch'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'Write to the atelier.',
                            'help' => 'The last two words are set in gold italic automatically.'],
                        'intro'   => ['type' => 'textarea', 'label' => 'Introduction',
                            'default' => 'For orders, bespoke enquiries, corporate gifting, or just to say hello. We reply within one business day.'],
                    ],
                ],

                'channels' => [
                    'label'  => 'Ways to reach you',
                    'help'   => 'Shown as a row of cards. Leave a card blank and it is not drawn.',
                    'fields' => [
                        'items' => [
                            'type'   => 'list',
                            'label'  => 'Cards',
                            'max'    => 4,
                            'fields' => [
                                'icon'  => ['type' => 'text', 'label' => 'Icon', 'help' => 'mail, phone, whatsapp or pin'],
                                'title' => ['type' => 'text', 'label' => 'Heading'],
                                'note'  => ['type' => 'text', 'label' => 'Small line beneath'],
                                'value' => ['type' => 'text', 'label' => 'What it says'],
                                'link'  => ['type' => 'text', 'label' => 'Where it goes',
                                    'help' => 'mailto:, tel:, https:// or a path on this site.'],
                            ],
                        ],
                    ],
                ],

                'invite' => [
                    'label'  => 'Say hello',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'Say hello'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'Let us compose something for you.'],
                        'body'    => ['type' => 'textarea', 'label' => 'Paragraph'],
                        'address_label' => ['type' => 'text', 'label' => 'Address heading', 'default' => 'Our atelier'],
                        'address' => ['type' => 'textarea', 'label' => 'Address', 'help' => 'One line per line.'],
                    ],
                ],

                'band' => [
                    'label'  => 'Photograph',
                    'fields' => [
                        'image'   => ['type' => 'image', 'label' => 'Image', 'help' => 'Wide, around 1920 × 700.'],
                        'caption' => ['type' => 'text', 'label' => 'Caption'],
                        'note'    => ['type' => 'text', 'label' => 'Line beneath the caption'],
                    ],
                ],

                'faq' => [
                    'label'  => 'Questions',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'default' => 'Frequently asked'],
                        'title'   => ['type' => 'text', 'label' => 'Heading', 'default' => 'Questions we often hear.'],
                        'items'   => [
                            'type'   => 'list',
                            'label'  => 'Questions',
                            'max'    => 12,
                            'fields' => [
                                'q' => ['type' => 'text', 'label' => 'Question'],
                                'a' => ['type' => 'textarea', 'label' => 'Answer'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        return $this->templates[$key] ?? $this->templates['standard'];
    }

    /** Every field's default, for a page that has not been filled in yet. */
    public function defaults(string $key): array
    {
        $out = [];

        foreach ($this->get($key)['sections'] as $section => $meta) {
            foreach ($meta['fields'] as $name => $field) {
                if ($field['type'] === 'list') {
                    $out[$section][$name] = [];

                    continue;
                }

                $out[$section][$name] = $field['default'] ?? '';
            }
        }

        return $out;
    }
}
