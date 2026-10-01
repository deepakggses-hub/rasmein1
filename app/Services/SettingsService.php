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
    /**
     * The journey this VISITOR is on.
     *
     * The shop still sets a default, but a corporate buyer can switch the whole
     * site into enquiry mode from the header — someone ordering two hundred
     * hampers is not going to use a basket, and someone buying one gift should
     * not be made to fill in an enquiry form.
     *
     * The switch is a plain cookie: it carries no personal data, is not a
     * credential, and losing it only means the site opens in its default mode.
     * It is deliberately NOT httpOnly, so the header can reflect the current
     * mode without waiting for a round trip.
     */
    /**
     * Set by setJourneyMode() during this request.
     *
     * IncomingRequest reads its cookies once, at construction, so writing to
     * $_COOKIE afterwards changes nothing it can see — the header would render
     * the old mode and only agree on the NEXT page.
     */
    private ?string $modeOverride = null;

    /**
     * Can this visitor buy, or may they only enquire?
     *
     * TWO SEPARATE DECISIONS, AND THE STORE'S ONE OUTRANKS THE VISITOR'S.
     *
     *  - `settings.journey_mode` is the SHOP's selling mode. Enquire there
     *    means the shop is not taking money online at all — no card, no
     *    checkout, every "Buy now" reads "Enquire now" for everybody.
     *  - the `rs_mode` cookie is the VISITOR's journey, retail or corporate. A
     *    corporate buyer wants a quote rather than a basket.
     *
     * So a visitor choice may only ESCALATE buy → enquire. It can never pull
     * someone out of a shop-wide enquiry mode, because that is not the
     * visitor's decision to make.
     *
     * THE BUG THIS FIXES. The cookie was consulted first, unconditionally —
     * and `Home::index()` stamps `rs_mode=buy_now` on every homepage visit. So
     * every visitor picked up a buy_now cookie on their way in, that cookie
     * outranked the setting, and the administrator's master switch did
     * nothing at all. Setting the shop to Enquire changed no page for anyone.
     */
    public function journeyMode(): string
    {
        $stored = $this->storedJourneyMode();

        // The shop's own decision. Not negotiable by a cookie or by a page.
        if ($stored === Rasmein::MODE_ENQUIRE) {
            return Rasmein::MODE_ENQUIRE;
        }

        if ($this->modeOverride !== null) {
            return $this->modeOverride;
        }

        $chosen = (string) (service('request')->getCookie(Rasmein::MODE_COOKIE) ?? '');

        if (in_array($chosen, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true)) {
            return $chosen;
        }

        return $stored;
    }

    /**
     * Is this visitor on the CORPORATE journey?
     *
     * Deliberately independent of the shop's selling mode. "We do not take
     * payment online" and "this person is buying two hundred hampers for their
     * staff" are different facts, and reading one from the other is what made
     * the master switch swap the whole catalogue to corporate audience and
     * light up the Corporate tab for every visitor.
     *
     * Used for the header switcher and for `products.audience` filtering —
     * anything answering "which catalogue and which journey", never "may this
     * person pay".
     */
    public function isCorporate(): bool
    {
        if ($this->modeOverride !== null) {
            return $this->modeOverride === Rasmein::MODE_ENQUIRE;
        }

        return (string) (service('request')->getCookie(Rasmein::MODE_COOKIE) ?? '')
            === Rasmein::MODE_ENQUIRE;
    }

    /**
     * The mode the STORE is set to — the admin master switch's value.
     *
     * Deliberately blind to the cookie and to setJourneyMode(). journeyMode()
     * answers "what should this visitor be shown", which a per-visitor choice
     * and the page being viewed both legitimately override. This answers "what
     * has the shop decided", and nothing about one browser may change that.
     *
     * THE BUG THIS FIXES. The admin panel used journeyMode() for the master
     * switch, so it was reading the administrator's OWN rs_mode cookie:
     *
     *  - the dropdown showed the wrong current mode;
     *  - switching to the mode the cookie happened to hold was answered with
     *    "Already set to that." and NOTHING WAS WRITTEN — reported from the
     *    field as the switch being completely stuck;
     *  - and when it did write, the audit entry recorded the wrong "from".
     *
     * Merely visiting /corporate sets that cookie, so almost any administrator
     * who had looked at their own storefront was in this state.
     */
    public function storedJourneyMode(): string
    {
        $mode = (string) $this->get('journey_mode', Rasmein::MODE_BUY);

        return in_array($mode, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true)
            ? $mode
            : Rasmein::MODE_BUY;
    }

    /**
     * Drop this browser's per-visitor choice, so it falls back to the store's.
     *
     * Used after an administrator changes the master switch: without it they
     * set the store to Enquire, see their own storefront still in Buy, and
     * reasonably conclude the switch did not work. It touches only the browser
     * performing the action.
     */
    public function forgetJourneyChoice(): void
    {
        $this->modeOverride = null;

        setcookie(Rasmein::MODE_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => service('request')->isSecure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[Rasmein::MODE_COOKIE]);
    }

    /**
     * Set the mode from the page being viewed.
     *
     * Landing on /corporate is a statement about why someone is here, and
     * leaving the switch reading "personalised" while showing corporate gifting
     * is a contradiction the visitor has to resolve by hand.
     *
     * Writes the cookie AND the request, so the header on THIS response already
     * reflects it — setting only the cookie would leave the switch a page
     * behind.
     */
    public function setJourneyMode(string $mode): void
    {
        if (! in_array($mode, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true)) {
            return;
        }

        /*
         * Compare against the VISITOR'S OWN CHOICE, never journeyMode().
         *
         * This method records which journey the visitor is on — it is the
         * corporate switch, not the shop's selling mode. journeyMode() folds
         * the shop's setting in, and once a shop is in enquiry mode that makes
         * the resolved journey permanently 'enquire_now' for everyone. The
         * guard then matched on arrival at /corporate, returned early, and no
         * cookie was ever written: the page did not turn corporate mode on,
         * the header still read "Personalised", and there was nothing to carry
         * to the next page.
         *
         * Reported from the field on a shop whose master switch was set to
         * Enquire — which is exactly the configuration that triggers it, and
         * the reason it did not show up on a default install.
         *
         * null means the visitor has expressed no preference yet, which is
         * never equal to a mode, so the first page to state one always writes.
         */
        $chosen = (string) (service('request')->getCookie(Rasmein::MODE_COOKIE) ?? '');

        $current = $this->modeOverride
            ?? (in_array($chosen, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true) ? $chosen : null);

        if ($current === $mode) {
            return;
        }

        // Read back within THIS request, before the cookie exists.
        $this->modeOverride = $mode;

        /*
         * PHP's setcookie(), not CI's helper.
         *
         * The helper queues onto the Response, which a view rendered mid-request
         * has already passed — the cookie simply never went out. The Mode
         * controller has always used the native call for the same reason.
         *
         * Not httpOnly, and a year: the header reads it client-side and it
         * carries no personal data.
         */
        setcookie(Rasmein::MODE_COOKIE, $mode, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'secure'   => service('request')->isSecure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        // And for anything reading $_COOKIE directly.
        $_COOKIE[Rasmein::MODE_COOKIE] = $mode;
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
