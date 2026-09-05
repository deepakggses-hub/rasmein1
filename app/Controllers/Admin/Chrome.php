<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\SettingModel;

/**
 * The header and the footer, in one place.
 *
 * These settings were scattered: the navigation under Appearance, the search
 * wording under the sign-in screen, the contact details under Shop identity,
 * the footer columns nowhere at all. Someone asked to "change the phone number
 * in the footer" had to know which of four screens owned it.
 *
 * This screen does not move them between groups — that would break the other
 * screens and the storefront's reads. It gathers them by KEY, which is what
 * they have in common.
 */
class Chrome extends AdminController
{
    /**
     * What this screen owns, in the order it is shown.
     *
     * `list` means one item per line. `long` means a paragraph. Everything else
     * is a single line.
     */
    private const FIELDS = [
        'Header' => [
            'design_nav' => [
                'label' => 'Navigation',
                'type'  => 'list',
                'help'  => 'One per line, as "Label | /path". A blank line is ignored.',
            ],
            'search_placeholders' => [
                'label' => 'Search box wording',
                'type'  => 'list',
                'help'  => 'One per line. The box types each, waits, wipes it and moves to the next. '
                    . 'Two or more are needed for the animation — with one it simply sits there.',
            ],
            'header_notice' => [
                'label' => 'Announcement bar',
                'type'  => 'text',
                'help'  => 'Shown above the header. Leave blank to hide it.',
            ],
            'header_notice_link' => [
                'label' => 'Announcement links to',
                'type'  => 'text',
                'help'  => 'A path on this site, e.g. /shop. Leave blank for no link.',
            ],
        ],

        'Footer' => [
            'footer_blurb' => [
                'label' => 'Introduction',
                'type'  => 'long',
                'help'  => 'The paragraph beside the logo.',
            ],
            'footer_columns' => [
                'label' => 'Link columns',
                'type'  => 'list',
                'help'  => 'One per line, as "Column | Label | /path". Lines sharing a column '
                    . 'name are grouped under it, in the order written.',
            ],
            'footer_note' => [
                'label' => 'Small print',
                'type'  => 'text',
                'help'  => 'Beside the copyright line.',
            ],
        ],

        'Contact and social' => [
            'store_support_email' => ['label' => 'Support email', 'type' => 'text'],
            'store_support_phone' => ['label' => 'Support phone', 'type' => 'text'],
            'store_whatsapp'      => ['label' => 'WhatsApp number', 'type' => 'text'],
            'social_instagram'    => ['label' => 'Instagram', 'type' => 'text'],
            'social_facebook'     => ['label' => 'Facebook', 'type' => 'text'],
            'social_pinterest'    => ['label' => 'Pinterest', 'type' => 'text'],
            'social_linkedin'     => ['label' => 'LinkedIn', 'type' => 'text'],
        ],
    ];

    public function index()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $keys = [];

        foreach (self::FIELDS as $group) {
            $keys = array_merge($keys, array_keys($group));
        }

        $rows = [];

        // By KEY, not by group — these live in four different groups, which is
        // exactly why they were hard to find in the first place.
        foreach (model(SettingModel::class)->whereIn('key_name', $keys)->findAll() as $row) {
            $rows[$row['key_name']] = $row['value'];
        }

        return $this->adminPage('admin/chrome/index', [
            'fields' => self::FIELDS,
            'values' => $rows,
        ], 'Header & footer');
    }

    public function save()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $model = model(SettingModel::class);

        foreach (self::FIELDS as $group) {
            foreach ($group as $key => $meta) {
                $posted = $this->request->getPost($key);

                if ($posted === null) {
                    continue;
                }

                $value = (string) $posted;

                if (($meta['type'] ?? '') === 'list') {
                    // Tidy a list on the way in: trim every line and drop the
                    // blank ones, so a stray return does not become an empty
                    // navigation item on the storefront.
                    $lines = array_values(array_filter(array_map(
                        'trim',
                        preg_split('/\R/u', $value) ?: []
                    )));

                    $value = implode("\n", $lines);
                }

                /*
                 * Keep each key in the group it already belongs to. Forcing
                 * them all into one would orphan them from the screens that
                 * still read them by group — Appearance, Shop identity and the
                 * sign-in screen all do.
                 */
                $existing = $model->where('key_name', $key)->first();

                $this->settings->set(
                    $key,
                    mb_substr(trim($value), 0, 4000),
                    'string',
                    $existing['group_name'] ?? 'design'
                );
            }
        }

        $this->settings->flush();
        service('audit')->log('updated', 'settings', 'chrome', null, 'Header and footer');

        return redirect()->to(site_url('admin/chrome'))->with('success', 'Header and footer saved.');
    }
}
