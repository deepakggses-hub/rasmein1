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

if (! function_exists('rs_icon')) {
    /**
     * An inline SVG icon.
     *
     * A function rather than a view partial, for two reasons learned the hard
     * way. CodeIgniter's view() keeps its data between calls unless told
     * otherwise, so one icon rendered with an explicit class silently became the
     * default for every icon after it — 23 of 25 came out the wrong size. And
     * esc($class, 'attr') encodes the space in "h-4 w-4" as &#x20;, which works
     * but is needless (CLAUDE.md §15.9).
     *
     * Inline SVG rather than an icon font: no extra request, no flash of missing
     * glyphs, and currentColor means each icon follows its link's state for free.
     */
    function rs_icon(string $name, string $class = 'h-4 w-4'): string
    {
        static $paths = null;

        if ($paths === null) {
            $paths = [
            'dashboard'    => '<path d="M3 12h7V3H3v9Zm0 9h7v-6H3v6Zm11 0h7V12h-7v9Zm0-18v6h7V3h-7Z"/>',
            'bell'         => '<path d="M12 3a6 6 0 0 0-6 6v3.6L4.5 16h15L18 12.6V9a6 6 0 0 0-6-6Z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
            'orders'       => '<path d="M4 7h16l-1.2 12.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8L4 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/>',
            'enquiries'    => '<path d="M4 5h16v11H9l-5 4V5Z"/><path d="M8 9h8M8 12.5h5"/>',
            'coupons'      => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/><path d="M14 6v12"/>',
            'products'     => '<path d="M12 3 3 7.5v9L12 21l9-4.5v-9L12 3Z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
            'categories'   => '<path d="M4 6h6v6H4zM14 6h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
            'giftbox'      => '<path d="M3 9h18v11H3zM3 9l1.5-4h15L21 9M12 5v15M8 5a2 2 0 1 1 4 0M16 5a2 2 0 1 0-4 0"/>',
            'pages'        => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
            'banners'      => '<path d="M3 6h18v9H3z"/><path d="m3 15 5-4 4 3 3-2 6 4"/>',
            'mailtemplate' => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
            'customers'    => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 16 0"/>',
            'staff'        => '<path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M2 20a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 20a6 6 0 0 0-5-5.9"/>',
            'roles'        => '<path d="M12 3 4 6v6c0 4.4 3.4 8.3 8 9 4.6-.7 8-4.6 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
            'reports'      => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'settings'     => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="m19.4 14-.6 1.4 1.2 2-2.6 2.6-2-1.2-1.4.6-.6 2.2h-3.6L9.2 19l-1.4-.6-2 1.2L3.2 17l1.2-2-.6-1.4L1.6 13V9.4l2.2-.6.6-1.4-1.2-2L5.8 2.8l2 1.2L9.2 3.4 9.8 1.2h3.6L14 3.4l1.4.6 2-1.2 2.6 2.6-1.2 2 .6 1.4 2.2.6V13l-2.2 1Z"/>',
            'mail'         => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
            'audit'        => '<path d="M12 8v4l3 2"/><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/>',
            'search'       => '<path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM21 21l-5-5"/>',
            'menu'         => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'close'        => '<path d="M6 6l12 12M18 6 6 18"/>',
            'store'        => '<path d="M4 9h16v11H4z"/><path d="M4 9 5.5 4h13L20 9M9 20v-6h6v6"/>',
            'logout'       => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h10"/>',
];
        }

        // The class is a developer-supplied literal, not user input, but keep it
        // to the characters a class can legitimately contain.
        $class = (string) preg_replace('/[^a-zA-Z0-9 _\/:\[\]\-]/', '', $class);

        return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="1.5" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . ($paths[$name] ?? $paths['dashboard'])
            . '</svg>';
    }
}
