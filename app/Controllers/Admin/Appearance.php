<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\SettingModel;

/**
 * Appearance — page width, grid density, corners and palette.
 *
 * These become CSS custom properties in the page head, so a change here
 * re-flows the storefront on the next load with no rebuild. DesignService
 * clamps and pattern-checks everything on the way out; this screen validates on
 * the way in as well, so a mistake is reported rather than silently corrected.
 */
class Appearance extends AdminController
{
    private const FIELDS = [
        'design_container', 'design_max_width', 'design_gutter',
        'design_card_min', 'design_card_min_dense', 'design_card_ratio',
        'design_radius', 'design_pill_radius',
        'design_color_deep', 'design_color_primary', 'design_color_accent',
        'design_color_surface', 'design_color_surface_alt',
        // design_marquee_text lives on the Homepage screen: it is copy, not
        // layout. The on/off flag stays here, in FLAGS.
        'design_nav',
    ];

    private const FLAGS = ['design_sticky_header', 'design_marquee'];

    public function index()
    {
        if ($denied = $this->deny('settings.view')) {
            return $denied;
        }

        $design = service('design');
        $values = [];

        foreach (array_merge(self::FIELDS, self::FLAGS) as $key) {
            $values[$key] = $design->get($key, '');
        }

        return $this->adminPage('admin/appearance/index', [
            'values'    => $values,
            'missing'   => count(array_filter(
                array_merge(self::FIELDS, self::FLAGS),
                static fn (string $k): bool => model(SettingModel::class)->where('key_name', $k)->countAllResults() === 0
            )),
            'canManage' => $this->can('settings.manage'),
        ], 'Appearance');
    }

    public function save()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $errors = [];

        $container = (string) $this->request->getPost('design_container');

        if (! in_array($container, ['full', 'wide', 'boxed'], true)) {
            $errors[] = 'That is not a page width option.';
        }

        foreach ([
            'design_color_deep' => 'Header and footer',
            'design_color_primary' => 'Primary buttons',
            'design_color_accent' => 'Gold accent',
            'design_color_surface' => 'Page background',
            'design_color_surface_alt' => 'Alternate band',
        ] as $key => $label) {
            $value = trim((string) $this->request->getPost($key));

            if ($value !== '' && preg_match('/^#[0-9a-f]{3,8}$/i', $value) !== 1) {
                $errors[] = $label . ' must be a hex colour, like #4A0C18.';
            }
        }

        // A card minimum wider than the dense one would make the "compact"
        // toggle show FEWER columns, which reads as a broken control.
        $wide  = (float) $this->request->getPost('design_card_min');
        $dense = (float) $this->request->getPost('design_card_min_dense');

        if ($wide > 0 && $dense > 0 && $dense > $wide) {
            $errors[] = 'The compact card cannot be wider than the comfortable one — the toggle would show fewer columns, not more.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        foreach (self::FIELDS as $key) {
            $value = $this->request->getPost($key);

            if ($value === null) {
                continue;
            }

            $this->settings->set($key, mb_substr(trim((string) $value), 0, 2000), 'string', 'design');
        }

        foreach (self::FLAGS as $key) {
            $this->settings->set($key, $this->request->getPost($key) !== null ? '1' : '0', 'bool', 'design');
        }

        $this->settings->flush();
        service('design')->forget();

        service('audit')->log('appearance_updated', 'settings', 'setting', null, 'Appearance updated');

        return redirect()->to(site_url('admin/appearance'))
            ->with('success', 'Appearance saved. Reload the storefront to see it.');
    }

    /** Put every appearance setting back to the shipped design. */
    public function reset()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        db_connect()->table('settings')->where('group_name', 'design')->delete();

        try {
            ob_start();
            \Config\Database::seeder()->call('DesignSettingSeeder');
            ob_end_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return redirect()->back()->with('error', 'Could not restore the defaults — see the log.');
        }

        $this->settings->flush();
        service('design')->forget();

        return redirect()->to(site_url('admin/appearance'))->with('success', 'Appearance put back to the original design.');
    }
}
