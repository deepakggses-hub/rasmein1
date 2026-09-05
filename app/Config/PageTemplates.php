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
