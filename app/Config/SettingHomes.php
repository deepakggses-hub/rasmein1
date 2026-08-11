<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Where a locked setting is actually edited.
 *
 * Some settings are deliberately read-only on the generic Settings screen —
 * they need a form that knows how to encrypt a password, handle an image
 * upload, or demand a typed confirmation. Marking them locked was right; saying
 * only "changed through its own guarded control" was not, because it named no
 * destination and the reader was left hunting.
 *
 * Worse, two of them pointed at a screen that does not exist: the payment
 * settings were seeded for a gateway that is still deferred. A message that
 * promises somewhere to go had better be true.
 *
 * So each entry either names a real destination, or says plainly that there
 * isn't one yet.
 */
class SettingHomes extends BaseConfig
{
    /**
     * Exact key matches. Checked before the group map below.
     *
     * @var array<string, array{label: string, url: string|null, permission: string|null, note: string|null}>
     */
    public array $byKey = [
        'journey_mode' => [
            'label'      => 'the Buy / Enquire panel at the top of this page',
            'url'        => 'admin/settings',
            'permission' => 'settings.journey_mode',
            'note'       => 'Switching it changes how the whole shop sells, so it asks you to type SWITCH first.',
        ],
        'payment_enabled' => [
            'label'      => null,
            'url'        => null,
            'permission' => null,
            'note'       => 'No screen yet — the payment gateway has not been built. Changing this would have no effect.',
        ],
        'payment_gateway' => [
            'label'      => null,
            'url'        => null,
            'permission' => null,
            'note'       => 'No screen yet — the payment gateway has not been built.',
        ],
    ];

    /**
     * Fallback by settings group.
     *
     * @var array<string, array{label: string, url: string, permission: string|null}>
     */
    public array $byGroup = [
        'mail'   => ['label' => 'Settings → Mail',          'url' => 'admin/mail',       'permission' => 'settings.manage'],
        'brand'  => ['label' => 'Settings → Shop identity', 'url' => 'admin/brand',      'permission' => 'settings.view'],
        'store'  => ['label' => 'Settings → Shop identity', 'url' => 'admin/brand',      'permission' => 'settings.view'],
        'social' => ['label' => 'Settings → Shop identity', 'url' => 'admin/brand',      'permission' => 'settings.view'],
        'design' => ['label' => 'Settings → Appearance',    'url' => 'admin/appearance', 'permission' => 'settings.view'],
        'home'   => ['label' => 'Content → Homepage',      'url' => 'admin/homepage',   'permission' => 'homepage.manage'],
    ];

    /**
     * Where a given setting is edited, or null when nowhere is.
     *
     * @return array{label: string|null, url: string|null, permission: string|null, note: string|null}|null
     */
    public function forSetting(string $key, string $group): ?array
    {
        if (isset($this->byKey[$key])) {
            return $this->byKey[$key];
        }

        if (isset($this->byGroup[$group])) {
            return $this->byGroup[$group] + ['note' => null];
        }

        return null;
    }
}
