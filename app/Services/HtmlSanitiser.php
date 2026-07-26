<?php

declare(strict_types=1);

namespace App\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Allowlist HTML sanitiser for staff-authored content.
 *
 * Runs on SAVE, not on render. Storing clean HTML means every read path is
 * safe by construction — including any future export, API or email template
 * that someone writes later and forgets to escape.
 *
 * Allowlist, never blocklist: anything not explicitly permitted is dropped.
 * A blocklist is a list of the attacks you happened to think of.
 *
 * Deliberately not a general-purpose purifier. It covers the formatting a
 * shop's content pages actually need — headings, paragraphs, lists, links,
 * emphasis, tables — and nothing else. No forms, no media, no styling.
 */
class HtmlSanitiser
{
    /**
     * Tag => attributes permitted on it.
     *
     * `style` appears widely because the editor expresses alignment, colour,
     * size and indentation as inline styles. It is NOT a blanket allowance —
     * see SAFE_CSS below: every declaration is parsed, its property checked
     * against an allowlist, and its value validated by pattern.
     */
    private const ALLOWED = [
        'p'          => ['style'],
        'br'         => [],
        'hr'         => [],
        'strong'     => [], 'b' => [],
        'em'         => [], 'i' => [],
        'u'          => [],
        's'          => [], 'strike' => [], 'del' => [], 'ins' => [],
        'sub'        => [], 'sup'    => [],
        'h1'         => ['style'], 'h2' => ['style'], 'h3' => ['style'],
        'h4'         => ['style'], 'h5' => ['style'], 'h6' => ['style'],
        'ul'         => ['style'], 'ol' => ['style', 'start', 'type'],
        'li'         => ['style'],
        'blockquote' => ['style'],
        'a'          => ['href', 'title', 'name'],
        'table'      => ['style', 'border', 'cellpadding', 'cellspacing'],
        'thead'      => [], 'tbody' => [], 'tfoot' => [], 'caption' => [],
        'tr'         => ['style'],
        'th'         => ['colspan', 'rowspan', 'style', 'scope'],
        'td'         => ['colspan', 'rowspan', 'style'],
        'figure'     => ['style'], 'figcaption' => [],
        'img'        => ['src', 'alt', 'title', 'width', 'height', 'style'],
        'code'       => [], 'pre' => ['style'],
        'span'       => ['style'],
        'div'        => ['style'],
    ];

    /**
     * CSS properties an author may set, each with a pattern its value must
     * match. Anything not listed is dropped; anything listed but failing its
     * pattern is dropped.
     *
     * This exists because allowing `style` naively is an XSS hole: historic and
     * current vectors include `background:url(javascript:...)`,
     * `width:expression(...)`, `behavior:url(...)` and `@import`. Validating
     * the VALUE, not just the property name, is what closes them — and no
     * property here accepts a url() at all.
     */
    private const SAFE_CSS = [
        'text-align'       => '/^(left|right|center|justify)$/i',
        'text-decoration'  => '/^(none|underline|line-through|overline)$/i',
        'font-weight'      => '/^(normal|bold|bolder|lighter|[1-9]00)$/i',
        'font-style'       => '/^(normal|italic|oblique)$/i',
        'font-size'        => '/^(\d{1,3}(\.\d+)?(px|pt|em|rem|%)|xx-small|x-small|small|medium|large|x-large|xx-large)$/i',
        'font-family'      => '/^[a-z0-9 ,\-\x27"]{1,120}$/i',
        'color'            => '/^(#[0-9a-f]{3,8}|rgba?\([\d\s,.%]+\)|hsla?\([\d\s,.%deg]+\)|[a-z]{3,20})$/i',
        'background-color' => '/^(#[0-9a-f]{3,8}|rgba?\([\d\s,.%]+\)|hsla?\([\d\s,.%deg]+\)|transparent|[a-z]{3,20})$/i',
        'padding-left'     => '/^\d{1,4}(\.\d+)?(px|em|rem|%)$/i',
        'margin-left'      => '/^\d{1,4}(\.\d+)?(px|em|rem|%)$/i',
        'text-indent'      => '/^\d{1,4}(\.\d+)?(px|em|rem|%)$/i',
        'direction'        => '/^(ltr|rtl)$/i',
        'width'            => '/^\d{1,4}(\.\d+)?(px|em|rem|%)$/i',
        'height'           => '/^(auto|\d{1,4}(\.\d+)?(px|em|rem|%))$/i',
        'vertical-align'   => '/^(top|middle|bottom|baseline|sub|super)$/i',
        'border-collapse'  => '/^(collapse|separate)$/i',
        'float'            => '/^(left|right|none)$/i',
        'line-height'      => '/^\d{1,3}(\.\d+)?(px|em|rem|%)?$/i',
    ];

    /**
     * Substrings that void a whole declaration on sight, whatever the property.
     * Checked against a whitespace-stripped, lowercased copy so
     * `u r l (` and `\75 rl(` style evasions do not slip past.
     */
    private const CSS_POISON = [
        'url(', 'expression(', 'javascript:', 'vbscript:', 'behavior:',
        '@import', '\\', '&#', '/*', '*/', '<',
    ];

    /** Elements removed together with everything inside them. */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'option',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
    ];

    /** URL schemes a link or image may use. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Strip control characters that can be used to break out of parsers.
        $html = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html);

        $document = new DOMDocument('1.0', 'UTF-8');

        // Malformed markup is expected — that is half the point of sanitising.
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="rs-sanitise-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            // Unparseable: fall back to plain text rather than storing anything.
            return $this->asPlainText($html);
        }

        $xpath = new DOMXPath($document);
        $root  = $document->getElementById('rs-sanitise-root');

        if ($root === null) {
            return $this->asPlainText($html);
        }

        // 1. Remove dangerous elements and their contents outright.
        foreach (self::STRIP_WITH_CONTENT as $tag) {
            $nodes = $xpath->query('.//' . $tag, $root);

            if ($nodes !== false) {
                foreach (iterator_to_array($nodes) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        // 2. Remove comments — they can hide conditional-comment payloads.
        $comments = $xpath->query('.//comment()', $root);

        if ($comments !== false) {
            foreach (iterator_to_array($comments) as $comment) {
                $comment->parentNode?->removeChild($comment);
            }
        }

        // 3. Walk everything else: unwrap unknown tags, scrub attributes.
        $this->scrub($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        $out = trim($out);

        return $out === '' ? null : $out;
    }

    /** Recursively clean a node's children. */
    private function scrub(DOMNode $node): void
    {
        // Snapshot: the list mutates as we unwrap and remove.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Not allowed, but its text may be legitimate — unwrap rather
                // than delete, so content is preserved and markup is not.
                $this->scrub($child);
                $this->unwrap($child);

                continue;
            }

            $this->scrubAttributes($child, self::ALLOWED[$tag]);
            $this->scrub($child);
        }
    }

    /** @param list<string> $permitted */
    private function scrubAttributes(DOMElement $element, array $permitted): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);

            // Everything not named is dropped. That covers every on* handler,
            // style, srcset, formaction, and whatever gets invented next.
            if (! in_array($name, $permitted, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! $this->isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'style') {
                $clean = $this->cleanStyle($attribute->value);

                $clean === ''
                    ? $element->removeAttribute('style')
                    : $element->setAttribute('style', $clean);

                continue;
            }

            // Numeric attributes must actually be numeric.
            if (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'border', 'cellpadding', 'cellspacing', 'start'], true)
                && preg_match('/^\d{1,5}$/', trim($attribute->value)) !== 1) {
                $element->removeAttribute($attribute->name);
            }
        }

        // An image with no alt is an accessibility gap; give it an empty one so
        // screen readers skip it rather than reading out the filename.
        if (strtolower($element->nodeName) === 'img' && ! $element->hasAttribute('alt')) {
            $element->setAttribute('alt', '');
        }

        // Links that leave the site should not hand over the opener.
        if (strtolower($element->nodeName) === 'a' && $element->hasAttribute('href')) {
            $href = $element->getAttribute('href');

            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                $element->setAttribute('rel', 'noopener noreferrer');
                $element->setAttribute('target', '_blank');
            }
        }
    }

    /**
     * Keep only declarations whose property is allowlisted AND whose value
     * matches that property's pattern. Everything else is discarded silently.
     */
    private function cleanStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);

            $property = strtolower(trim($property));
            $value    = trim($value);

            if (! isset(self::SAFE_CSS[$property]) || $value === '') {
                continue;
            }

            // Poison check on a whitespace-free copy, so "url ( x )" and
            // "URL(" are caught along with "url(".
            $needle = strtolower((string) preg_replace('/\s+/', '', $value));

            foreach (self::CSS_POISON as $poison) {
                if (str_contains($needle, $poison)) {
                    continue 2;
                }
            }

            if (preg_match(self::SAFE_CSS[$property], $value) !== 1) {
                continue;
            }

            $kept[] = $property . ': ' . $value;
        }

        // A style attribute long enough to be a payload is not a style
        // attribute anyone typed on purpose.
        $out = implode('; ', $kept);

        return strlen($out) > 500 ? '' : $out;
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Relative and anchor links are fine.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        // Reject anything that hides a scheme behind whitespace or entities,
        // e.g. "java\0script:" or "jav&#x09;ascript:".
        $normalised = strtolower((string) preg_replace('/\s+/', '', $url));

        foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $bad) {
            if (str_starts_with($normalised, $bad)) {
                return false;
            }
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            // No scheme and not relative — treat as relative, which is safe.
            return ! str_contains($normalised, ':');
        }

        return in_array(strtolower((string) $scheme), self::SAFE_SCHEMES, true);
    }

    /** Replace an element with its children. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function asPlainText(string $html): ?string
    {
        $text = trim(strip_tags($html));

        return $text === '' ? null : '<p>' . esc($text) . '</p>';
    }
}
