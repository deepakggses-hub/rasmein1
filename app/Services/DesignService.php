<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turns the appearance settings into CSS custom properties.
 *
 * WHY CUSTOM PROPERTIES RATHER THAN CLASSES
 *
 * The alternative — generating Tailwind classes from settings — cannot work,
 * because Tailwind compiles at build time and the settings change at runtime.
 * Emitting a small block of variables into the head means the compiled
 * stylesheet stays static while the layout stays adjustable: components are
 * written against var(--rs-*), so changing the container width or the card
 * minimum re-flows the entire site with no rebuild.
 *
 * Values are validated and clamped here rather than trusted, because they land
 * inside a <style> block. A colour that is not a colour, or a width with a
 * semicolon in it, would otherwise be an injection point.
 */
class DesignService
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    /** @return array<string, string> */
    private function settings(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        try {
            foreach (db_connect()->table('settings')
                ->select('key_name, value')
                ->where('group_name', 'design')
                ->get()->getResultArray() as $row) {
                $this->cache[$row['key_name']] = (string) $row['value'];
            }
        } catch (\Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }

    public function get(string $key, string $fallback = ''): string
    {
        $value = $this->settings()[$key] ?? '';

        return $value !== '' ? $value : $fallback;
    }

    public function flag(string $key, bool $fallback = true): bool
    {
        $value = $this->settings()[$key] ?? null;

        return $value === null || $value === '' ? $fallback : $value === '1';
    }

    /** A hex colour, or the fallback when it is not one. */
    private function colour(string $key, string $fallback): string
    {
        $value = trim($this->get($key, $fallback));

        return preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1 ? $value : $fallback;
    }

    /** A number within bounds, formatted with a unit. */
    private function number(string $key, float $fallback, float $min, float $max, string $unit = ''): string
    {
        $value = (float) ($this->get($key, (string) $fallback));
        $value = max($min, min($max, $value));

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . $unit;
    }

    /**
     * The whole block, ready to drop into a <style> tag.
     *
     * Everything is clamped and pattern-checked, so nothing here can carry a
     * closing brace or a semicolon out of a setting and into the stylesheet.
     */
    public function cssVariables(): string
    {
        $container = $this->get('design_container', 'full');
        $maxWidth  = $this->number('design_max_width', 1800, 960, 3840);

        // Full bleed means no cap at all. `none` is a real max-width value, so
        // the same declaration serves every mode.
        $cap = $container === 'full'
            ? 'none'
            : ($container === 'boxed' ? min(1440.0, (float) $maxWidth) . 'px' : $maxWidth . 'px');

        $vars = [
            '--rs-container'     => $cap,
            // clamp() is what makes the gutter work from a 320px phone to an
            // ultrawide without a breakpoint at every step.
            '--rs-gutter'        => 'clamp(1rem, 3.5vw, ' . $this->number('design_gutter', 3, 1, 8, 'rem') . ')',
            '--rs-card-min'      => $this->number('design_card_min', 17, 10, 30, 'rem'),
            '--rs-card-min-dense'=> $this->number('design_card_min_dense', 13.5, 8, 24, 'rem'),
            '--rs-card-ratio'    => $this->number('design_card_ratio', 0.82, 0.4, 2),
            '--rs-radius'        => $this->number('design_radius', 2, 0, 32, 'px'),
            '--rs-pill'          => $this->number('design_pill_radius', 999, 0, 999, 'px'),
            '--rs-deep'          => $this->colour('design_color_deep', '#4A0C18'),
            '--rs-primary'       => $this->colour('design_color_primary', '#5E1F3D'),
            '--rs-accent'        => $this->colour('design_color_accent', '#C6A15B'),
            '--rs-surface'       => $this->colour('design_color_surface', '#FBF8F4'),
            '--rs-surface-alt'   => $this->colour('design_color_surface_alt', '#F5EEE6'),
        ];

        $out = '';

        foreach ($vars as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }

        return ':root{' . $out . '}';
    }

    /** @return array<string, string> The raw values, for the admin form. */
    public function all(): array
    {
        return $this->settings();
    }

    public function forget(): void
    {
        $this->cache = null;
    }
}
