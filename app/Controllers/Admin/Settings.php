<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\SettingModel;
use Config\Rasmein;
use Config\SettingHomes;

/**
 * Runtime settings.
 *
 * The Buy/Enquire master switch sits behind its OWN permission
 * (settings.journey_mode), separate from settings.manage — it changes how the
 * entire store sells, so a staff account that may edit a delivery charge should
 * not necessarily be able to flip it (CLAUDE.md §6).
 */
class Settings extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('settings.view')) {
            return $denied;
        }

        $model  = model(SettingModel::class);
        $groups = [];

        /*
         * Groups with a screen of their own are excluded rather than shown as a
         * wall of read-only rows. Mail is the clearest case: rendering it here
         * would put the encrypted SMTP password in a text input and re-save the
         * ciphertext as if it were plain. Appearance and Shop identity need
         * colour pickers and image uploads this screen cannot offer.
         *
         * Anything still locked and visible gets a link to where it IS edited —
         * see Config\SettingHomes.
         */
        foreach ($model->whereNotIn('group_name', ['mail', 'brand', 'store', 'social', 'design', 'home', 'auth'])
            ->orderBy('group_name', 'ASC')->orderBy('sort_order', 'ASC')->findAll() as $row) {
            $groups[$row['group_name']][] = $row;
        }

        return $this->adminPage('admin/settings/index', [
            'groups'        => $groups,
            'journeyMode'   => $this->settings->journeyMode(),
            'journeyModes'  => config(Rasmein::class)->journeyModes,
            'canManage'     => $this->can('settings.manage'),
            'canSwitchMode' => $this->can('settings.journey_mode'),
            'homes'         => $this->resolveHomes($groups),
        ], 'Settings');
    }

    /** The master switch. Deliberately its own endpoint and its own permission. */
    /**
     * Work out, for each locked setting, where it is edited and whether this
     * administrator can get there.
     *
     * Resolved here rather than in the view: a CodeIgniter template's $this is
     * the View renderer, not the controller, so calling $this->can() there
     * would be a fatal error the moment a locked row rendered.
     *
     * @param array<string, array<int, array<string, mixed>>> $groups
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolveHomes(array $groups): array
    {
        $map   = config(SettingHomes::class);
        $homes = [];

        foreach ($groups as $groupName => $rows) {
            foreach ($rows as $row) {
                if ((int) ($row['is_locked'] ?? 0) !== 1) {
                    continue;
                }

                $home = $map->forSetting((string) $row['key_name'], (string) $groupName);

                if ($home === null) {
                    $homes[$row['key_name']] = ['state' => 'unknown'];

                    continue;
                }

                $permitted = $home['permission'] === null || $this->can((string) $home['permission']);

                $homes[$row['key_name']] = [
                    'state' => ! empty($home['url']) && $permitted
                        ? 'linked'
                        : (! empty($home['label']) ? 'blocked' : 'none'),
                    'label' => $home['label'] ?? null,
                    'url'   => $home['url'] ?? null,
                    'note'  => $home['note'] ?? null,
                ];
            }
        }

        return $homes;
    }

    public function switchJourney()
    {
        if ($denied = $this->deny('settings.journey_mode')) {
            return $denied;
        }

        $mode  = (string) $this->request->getPost('journey_mode');
        $valid = [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE];

        if (! in_array($mode, $valid, true)) {
            return redirect()->back()->with('error', 'That is not a journey mode.');
        }

        $previous = $this->settings->journeyMode();

        if ($previous === $mode) {
            return redirect()->back()->with('success', 'Already set to that.');
        }

        // Confirmation phrase required: this is the single most consequential
        // control in the panel, and a mis-click changes every product page.
        if (strtoupper(trim((string) $this->request->getPost('confirm'))) !== 'SWITCH') {
            return redirect()->back()->with('error', 'Type SWITCH to confirm the change.');
        }

        $this->settings->set('journey_mode', $mode);

        service('audit')->log(
            'journey_mode_switched',
            'settings',
            'setting',
            null,
            'Store switched from ' . $previous . ' to ' . $mode,
            ['journey_mode' => $previous],
            ['journey_mode' => $mode]
        );

        log_message('notice', 'Journey mode switched {from} → {to} by admin {id}', [
            'from' => $previous,
            'to'   => $mode,
            'id'   => (string) session('admin_id'),
        ]);

        return redirect()->back()->with(
            'success',
            $mode === Rasmein::MODE_ENQUIRE
                ? 'The store now captures enquiries. Nothing will be charged online.'
                : 'The store now takes orders directly.'
        );
    }

    public function update()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $model   = model(SettingModel::class);
        $posted  = (array) $this->request->getPost('settings');
        $changed = 0;

        foreach ($posted as $key => $value) {
            $setting = $model->where('key_name', (string) $key)->first();

            if ($setting === null) {
                continue;
            }

            // Locked settings are changed through their own guarded endpoints,
            // never through this bulk form.
            if ((int) $setting['is_locked'] === 1) {
                continue;
            }

            $normalised = $setting['value_type'] === 'bool'
                ? (in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0')
                : (string) $value;

            if ($normalised === (string) $setting['value']) {
                continue;
            }

            $model->update($setting['id'], ['value' => $normalised]);

            service('audit')->log(
                'setting_changed',
                'settings',
                'setting',
                (int) $setting['id'],
                $setting['key_name'],
                ['value' => $setting['value']],
                ['value' => $normalised]
            );

            $changed++;
        }

        // An unchecked checkbox posts nothing, so booleans absent from the
        // payload must be written to 0 explicitly.
        foreach ($model->where('value_type', 'bool')->where('is_locked', 0)->findAll() as $flag) {
            if (! array_key_exists($flag['key_name'], $posted) && (string) $flag['value'] !== '0') {
                $model->update($flag['id'], ['value' => '0']);
                service('audit')->log(
                    'setting_changed', 'settings', 'setting', (int) $flag['id'],
                    $flag['key_name'], ['value' => $flag['value']], ['value' => '0']
                );
                $changed++;
            }
        }

        $this->settings->flush();

        return redirect()->back()->with(
            'success',
            $changed === 0 ? 'Nothing changed.' : $changed . ' setting' . ($changed === 1 ? '' : 's') . ' updated.'
        );
    }
}
