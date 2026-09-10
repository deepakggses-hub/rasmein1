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
        /*
         * 'inherit' means "follow the site", so it has to be RESOLVED, not
         * passed through — an unresolved 'inherit' matched neither branch below
         * and every card said "Add to cart" even in corporate mode.
         */
        $mode = service('settings')->resolveItemMode($mode ?? rs_journey_mode());

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

if (! function_exists('rs_collection_url')) {
    /**
     * Where an occasion or collection lives.
     *
     * ONE place decides, so moving the address again is one edit rather than a
     * hunt through views, facets and the sitemap — which is exactly how the old
     * root URL ended up hard-coded in four of them.
     */
    function rs_collection_url(string $slug): string
    {
        return site_url('collection/' . trim($slug, '/'));
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
            // Single-colour Google mark, so it takes the button's ink rather
            // than dropping a four-colour logo into a monochrome row.
            'google'       => '<path d="M12 10.6v3.1h4.4a3.9 3.9 0 0 1-4.4 3 4.7 4.7 0 1 1 3-8.3l2.2-2.2A7.8 7.8 0 1 0 12 19.9c4.4 0 7.4-3.1 7.4-7.5 0-.6 0-1.2-.2-1.8H12Z"/>',
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
            'heart'        => '<path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9Z"/>',
            'bag'          => '<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
            'user'         => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 16 0"/>',
            'arrow-right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'arrow-left'   => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
            'pin'          => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
            'briefcase'    => '<rect x="2.5" y="7" width="19" height="13" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M2.5 12h19"/>',
            'cake'         => '<path d="M4 20h16v-6a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v6Z"/><path d="M4 16h16"/><path d="M9 8V6M12 8V5M15 8V6"/>',
            'calendar'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M12 17a2 2 0 0 1-2-2c0-1.5 2-2.5 2-2.5s2 1 2 2.5a2 2 0 0 1-2 2Z"/>',
            'badge'        => '<path d="m12 3 2.2 1.6 2.7-.2.8 2.6 2.2 1.6-1 2.5 1 2.5-2.2 1.6-.8 2.6-2.7-.2L12 21l-2.2-1.6-2.7.2-.8-2.6L4.1 15.4l1-2.5-1-2.5 2.2-1.6.8-2.6 2.7.2Z"/><path d="m9.5 12 1.8 1.8 3.2-3.6"/>',
            'cheers'       => '<path d="M6 3h5l-1 8a2.5 2.5 0 0 1-5 0L6 3ZM13 3h5l1 8a2.5 2.5 0 0 1-5 0l-1-8Z"/><path d="M8 13v7M16 13v7M5 21h6M13 21h6"/>',
            'user-plus'    => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M18 8v6M15 11h6"/>',
            'namaste'      => '<path d="M12 3v9"/><path d="M12 12 8.5 8.5A2 2 0 0 0 5 10v4a7 7 0 0 0 7 7 7 7 0 0 0 7-7v-4a2 2 0 0 0-3.5-1.5L12 12Z"/>',
            'gift'         => '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M3 13h18M12 9v12"/><path d="M12 9S9.5 3 7.5 4.5 10 9 12 9Zm0 0s2.5-6 4.5-4.5S14 9 12 9Z"/>',
            'tag'          => '<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'sort'         => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'star'         => '<path d="m12 3 2.6 5.6 6 .8-4.4 4.2 1.1 6L12 16.8 6.7 19.6l1.1-6L3.4 9.4l6-.8L12 3Z"/>',
            'check'        => '<path d="m5 12 5 5L20 7"/>',
            'grid'         => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
            'rows'         => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'instagram'    => '<path d="M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Z"/><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 7h.01"/>',
            'facebook'     => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8.5A.5.5 0 0 1 14 8Z"/>',
            'pinterest'    => '<path d="M12 3a9 9 0 0 0-3.3 17.4c-.1-.8-.1-2 .1-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.5 1.8-2.5.9 0 1.3.6 1.3 1.4 0 .9-.6 2.2-.9 3.4-.2 1 .5 1.9 1.5 1.9 1.9 0 3.2-2.4 3.2-5.2 0-2.2-1.4-3.8-4-3.8a4.6 4.6 0 0 0-4.8 4.6c0 .9.3 1.5.7 2 .2.2.2.3.1.5l-.2.8c-.1.3-.2.4-.5.2-1.3-.5-1.9-2-1.9-3.6 0-2.7 2.3-6 6.8-6 3.6 0 6 2.6 6 5.4 0 3.7-2 6.4-5 6.4-1 0-2-.5-2.3-1.2l-.6 2.4c-.2.8-.7 1.8-1.1 2.4A9 9 0 1 0 12 3Z"/>',
            'linkedin'     => '<path d="M4 9h4v11H4zM6 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM11 20V9h4v1.6a4 4 0 0 1 6 3.4V20h-4v-5a2 2 0 0 0-4 0v5h-2Z"/>',
            'whatsapp'     => '<path d="M3 21l1.7-4.5A8 8 0 1 1 8 20.3L3 21Z"/><path d="M9 10c0 3 2 5 5 5"/>',
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

if (! function_exists('rs_url')) {
    /**
     * A URL for an asset path, safe to place in an attribute.
     *
     * This exists because esc($url, 'attr') entity-encodes "/" and ":" into
     * &#x2F; and &#x3A;. Browsers decode them, so the link works and the bug
     * hides — which is precisely why it has now been introduced four separate
     * times in this project (CLAUDE.md §15.9).
     *
     * The path here is generated by ImageUploadService (hex filename under a
     * fixed directory), so there is nothing user-controlled to escape. What
     * DOES need guarding is a path escaping its directory, so that is checked
     * and refused.
     */
    function rs_url(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '' || str_contains($path, '..')) {
            return '';
        }

        /*
         * An absolute URL is already finished — most often because it came from
         * rs_image(), which calls base_url() itself. Returning '' here meant
         * rs_url(rs_image(...)) produced src="" and every product image on the
         * shop was blank. Pass it through instead; the traversal guard above is
         * what actually matters.
         */
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        // Any other scheme (javascript:, data:) has no business in an asset URL.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1) {
            return '';
        }

        return base_url(ltrim($path, '/'));
    }
}

if (! function_exists('rs_picture')) {
    /**
     * A responsive <picture> for a stored image.
     *
     * WHY NOT JUST <img src>
     *
     * A card 400 CSS pixels wide on a 2x phone needs 800 real pixels; the same
     * card on a wide monitor might be 600. One file cannot be right for both —
     * either it is soft somewhere or it is wasteful everywhere. `srcset` hands
     * the browser the ladder and lets it choose, and `sizes` tells it how wide
     * the slot will actually be, which it cannot work out before the CSS loads.
     *
     * WebP is offered first because it is roughly 30% smaller at the same
     * visual quality; the original format follows for anything that cannot
     * read it.
     *
     * Variants are found by convention beside the original, so nothing extra is
     * stored in the database and an image uploaded before this existed still
     * works — it simply has no ladder and falls back to the single file.
     *
     * @param string|null $path  Relative path as stored, e.g. uploads/products/…
     * @param string      $sizes The `sizes` attribute — describe the SLOT
     * @param array<string, string> $attrs Extra attributes for the <img>
     */
    function rs_picture(?string $path, string $sizes = '100vw', array $attrs = []): string
    {
        $src = rs_image($path, $attrs['_type'] ?? 'products');
        unset($attrs['_type']);

        $defaults = [
            'alt'      => '',
            'loading'  => 'lazy',
            'decoding' => 'async',
        ];

        $attrs = $attrs + $defaults;

        // A placeholder or an absolute URL from elsewhere has no ladder.
        $relative = trim((string) $path);
        $hasLadder = $relative !== ''
            && ! str_contains($relative, '..')
            && str_starts_with($relative, 'uploads/');

        $render = static function (array $a): string {
            $out = '';

            foreach ($a as $key => $value) {
                if ($value === null || $value === false) {
                    continue;
                }

                /*
                 * src, srcset and sizes are assembled here from base_url() and a
                 * developer-supplied literal — nothing user-controlled reaches
                 * them. Escaping would only turn every slash into &#x2F;, which
                 * browsers decode anyway but which makes the output impossible
                 * to read or grep. Everything else (alt, class, data-*) IS
                 * escaped, because those can carry a product name.
                 */
                // class is a developer literal here too. alt is NOT on this
                 // list: it carries a product name a shop typed.
                $raw = in_array($key, ['src', 'srcset', 'sizes', 'class'], true);

                $out .= ' ' . $key . '="' . ($raw ? $value : esc((string) $value, 'attr')) . '"';
            }

            return $out;
        };

        if (! $hasLadder) {
            return '<img' . $render(['src' => $src] + $attrs) . '>';
        }

        $dot  = strrpos($relative, '.');
        $stem = $dot === false ? $relative : substr($relative, 0, $dot);
        $ext  = $dot === false ? 'jpg' : strtolower(substr($relative, $dot + 1));

        $jpegSet = [];
        $webpSet = [];

        foreach (config(\Config\Rasmein::class)->imageWidths as $width) {
            $variant = $stem . '-' . $width . '.' . $ext;

            if (is_file(FCPATH . $variant)) {
                $jpegSet[] = base_url($variant) . ' ' . $width . 'w';
            }

            $webpVariant = $stem . '-' . $width . '.webp';

            if (is_file(FCPATH . $webpVariant)) {
                $webpSet[] = base_url($webpVariant) . ' ' . $width . 'w';
            }
        }

        // Nothing generated — an older upload. The single file still works.
        if ($jpegSet === [] && $webpSet === []) {
            return '<img' . $render(['src' => $src] + $attrs) . '>';
        }

        $img = '<img' . $render(
            ['src' => $src]
            + ($jpegSet !== [] ? ['srcset' => implode(', ', $jpegSet), 'sizes' => $sizes] : [])
            + $attrs
        ) . '>';

        if ($webpSet === []) {
            return $img;
        }

        return '<picture>'
            . '<source type="image/webp" srcset="' . implode(', ', $webpSet) . '" sizes="' . $sizes . '">'
            . $img
            . '</picture>';
    }
}
