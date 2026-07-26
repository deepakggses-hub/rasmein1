<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingModel;
use Config\Rasmein;

/**
 * Reads and writes the runtime settings an admin controls.
 *
 * The whole table is loaded once per request and cached, because the journey
 * mode is needed on nearly every page. Writes bust the cache immediately so a
 * mode switch takes effect on the very next request — never on a delay.
 */
class SettingsService
{
    private const CACHE_KEY = 'rasmein_settings';
    private const CACHE_TTL = 3600;

    /** @var array<string, array{value: string|null, value_type: string}>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly SettingModel $model
    ) {
    }

    /**
     * @return array<string, array{value: string|null, value_type: string}>
     */
    private function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $cached = cache(self::CACHE_KEY);

        if (is_array($cached)) {
            return $this->loaded = $cached;
        }

        $rows = [];

        // A missing settings table (first install, mid-migration) must not
        // take the whole site down — fall back to defaults.
        try {
            foreach ($this->model->findAll() as $row) {
                $rows[$row['key_name']] = [
                    'value'      => $row['value'],
                    'value_type' => $row['value_type'],
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Settings could not be loaded: {msg}', ['msg' => $e->getMessage()]);

            return $this->loaded = [];
        }

        cache()->save(self::CACHE_KEY, $rows, self::CACHE_TTL);

        return $this->loaded = $rows;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $rows = $this->all();

        if (! isset($rows[$key])) {
            return $default;
        }

        return $this->cast($rows[$key]['value'], $rows[$key]['value_type'], $default);
    }

    private function cast(?string $value, string $type, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return match ($type) {
            'int'     => (int) $value,
            'decimal' => (float) $value,
            'bool'    => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode($value, true) ?? $default,
            default   => $value,
        };
    }

    /**
     * The site-wide journey mode. This is the authoritative read used by
     * checkout — an unrecognised or missing value falls back to Buy, never
     * to "whatever the client asked for".
     */
    public function journeyMode(): string
    {
        $mode = (string) $this->get('journey_mode', Rasmein::MODE_BUY);

        return in_array($mode, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true)
            ? $mode
            : Rasmein::MODE_BUY;
    }

    public function isEnquireMode(): bool
    {
        return $this->journeyMode() === Rasmein::MODE_ENQUIRE;
    }

    /**
     * Resolve the journey for one item. A product or box pinned to a mode
     * overrides the site switch; 'inherit' follows it.
     */
    public function resolveItemMode(?string $itemMode): string
    {
        if ($itemMode === Rasmein::MODE_BUY || $itemMode === Rasmein::MODE_ENQUIRE) {
            return $itemMode;
        }

        return $this->journeyMode();
    }

    /**
     * Write a setting, creating it if absent.
     *
     * $group matters for a key that does not exist yet: without it a new key is
     * filed under 'general', which is how a freshly uploaded logo ended up
     * somewhere its own resolver was not looking. Callers that own a group
     * should say so.
     */
    public function set(string $key, mixed $value, string $type = 'string', ?string $group = null): bool
    {
        $stored = $type === 'json' ? json_encode($value) : (string) $value;

        $existing = $this->model->where('key_name', $key)->first();

        $ok = $existing !== null
            ? $this->model->update($existing['id'], ['value' => $stored])
            : $this->model->insert([
                'key_name'   => $key,
                'value'      => $stored,
                'value_type' => $type,
                'group_name' => $group ?? 'general',
                'is_locked'  => $group === null ? 0 : 1,
            ]) !== false;

        $this->flush();

        return (bool) $ok;
    }

    public function flush(): void
    {
        $this->loaded = null;
        cache()->delete(self::CACHE_KEY);
    }

    /** @return array<string, mixed> Settings safe to expose to the storefront. */
    public function publicSettings(): array
    {
        $out = [];

        foreach ($this->model->where('is_public', 1)->findAll() as $row) {
            $out[$row['key_name']] = $this->cast($row['value'], $row['value_type'], null);
        }

        return $out;
    }
}
