<?php

declare(strict_types=1);

/**
 * Rasmein view helpers.
 *
 * Loaded globally from app/Config/Autoload.php. Keep these presentational —
 * anything that decides money or eligibility belongs in a Service, not here.
 */

use App\Services\SettingsService;
use Config\Rasmein;

if (! function_exists('rs_money')) {
    /**
     * Format an amount as Indian rupees using the Indian digit grouping
     * (1,00,000 — not 100,000). Falls back gracefully without ext-intl.
     */
    function rs_money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $config = config(Rasmein::class);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
            $formatted = (string) $formatter->format($amount);
        } else {
            $formatted = number_format($amount, 2, '.', ',');
        }

        return $withSymbol ? $config->currencySymbol . $formatted : $formatted;
    }
}

if (! function_exists('rs_setting')) {
    /** Read a runtime admin setting. */
    function rs_setting(string $key, mixed $default = null): mixed
    {
        return service('settings')->get($key, $default);
    }
}

if (! function_exists('rs_journey_mode')) {
    /**
     * The site-wide journey. Always resolved from the database, never from
     * a form field or query string.
     */
    function rs_journey_mode(): string
    {
        return service('settings')->journeyMode();
    }
}

if (! function_exists('rs_is_enquire_mode')) {
    function rs_is_enquire_mode(): bool
    {
        return rs_journey_mode() === Rasmein::MODE_ENQUIRE;
    }
}

if (! function_exists('rs_cta_label')) {
    /**
     * The primary action label for a given journey. Used everywhere so the
     * button, the toast and the page heading always agree.
     */
    function rs_cta_label(?string $mode = null, string $variant = 'primary'): string
    {
        $mode = $mode ?? rs_journey_mode();

        if ($mode === Rasmein::MODE_ENQUIRE) {
            return match ($variant) {
                'add'   => 'Add to enquiry',
                'cart'  => 'Enquiry list',
                'short' => 'Enquire',
                default => 'Enquire now',
            };
        }

        return match ($variant) {
            'add'   => 'Add to cart',
            'cart'  => 'Cart',
            'short' => 'Buy',
            default => 'Buy now',
        };
    }
}

if (! function_exists('rs_image')) {
    /**
     * Resolve a stored image path to a URL, with a graceful placeholder when
     * the record has no image yet.
     */
    function rs_image(?string $path, string $type = 'products'): string
    {
        if ($path === null || trim($path) === '') {
            return base_url('assets/img/placeholder-' . $type . '.svg');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return base_url(ltrim($path, '/'));
    }
}

if (! function_exists('rs_asset')) {
    /**
     * Cache-busted asset URL. Uses the file mtime so a deploy invalidates
     * the browser cache without a manual version bump.
     */
    function rs_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = FCPATH . $path;
        $stamp = is_file($file) ? (string) filemtime($file) : '1';

        return base_url($path) . '?v=' . $stamp;
    }
}

if (! function_exists('rs_excerpt')) {
    function rs_excerpt(?string $text, int $chars = 120): string
    {
        /*
         * Several fields now hold HTML from the rich text editor, where user
         * content is stored escaped — "Hand-picked &amp; packed". Stripping the
         * tags alone leaves those entities in place, and the caller's esc()
         * then encodes them a second time, so a customer sees the literal
         * "&amp;" or "&rsquo;" on the card. Decoding here means the caller gets
         * real text to escape exactly once.
         *
         * The second strip matters: decoding can turn a stored "&lt;script&gt;"
         * back into "<script>". Callers do escape this, but a plain-text
         * excerpt should contain no tags whatever the caller does with it.
         * (Same reasoning as MailService::toPlainText.)
         */
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('#</?[a-z][^>]*>#i', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $chars) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $chars), " \t\n\r\0\x0B.,;:") . '…';
    }
}

if (! function_exists('rs_active')) {
    /** Returns $class when the current URI matches, for nav highlighting. */
    function rs_active(string $uriPattern, string $class = 'is-active'): string
    {
        $current = trim(service('request')->getUri()->getPath(), '/');
        $pattern = trim($uriPattern, '/');

        if ($pattern === '') {
            return $current === '' ? $class : '';
        }

        return ($current === $pattern || str_starts_with($current, $pattern . '/')) ? $class : '';
    }
}

if (! function_exists('rs_user_agent')) {
    /**
     * The user agent string, or null when there is not one.
     *
     * getUserAgent() exists on IncomingRequest but NOT on CLIRequest, so
     * calling it unguarded crashes any code that also runs from `spark` — a
     * cron job placing an order, a queue worker, an import. Length-capped to
     * fit the varchar(255) columns that store it.
     */
    function rs_user_agent(int $maxLength = 255): ?string
    {
        $request = service('request');

        if (! method_exists($request, 'getUserAgent')) {
            return null;
        }

        $agent = (string) $request->getUserAgent();

        return $agent === '' ? null : mb_substr($agent, 0, $maxLength);
    }
}
