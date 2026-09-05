<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The long-lived identity of a visitor who has not signed in.
 *
 * WHY A COOKIE AND NOT THE SESSION
 *
 * A PHP session cookie dies when the browser closes, and the session itself
 * expires in two hours. Someone who adds three hampers on Monday evening and
 * comes back Tuesday morning would find an empty basket — which reads as the
 * shop losing their things, not as a technical detail.
 *
 * SECURITY
 *
 * This token IS the credential for a guest basket, so:
 *
 *  - httpOnly, so a script on the page (or an injected one) cannot read it.
 *  - SameSite=Lax, so it is not sent on cross-site POSTs.
 *  - Secure whenever the request is HTTPS.
 *  - 32 random bytes from random_bytes(), not anything derived from the
 *    visitor — a guessable token would let one person read another's basket.
 *  - It is REPLACED on login, so a shared or stolen token cannot follow someone
 *    into their account.
 *
 * It deliberately carries no personal data and is not used for tracking beyond
 * remembering a basket.
 */
class VisitorService
{
    public const COOKIE = 'rs_visitor';

    private const LIFETIME = 60 * 60 * 24 * 365;  // a year

    private ?string $token = null;

    /**
     * The visitor's token, minting one if this is their first visit.
     *
     * Call this only when something is actually being saved. Issuing a cookie
     * to every passer-by is both rude and pointless.
     */
    public function __construct()
    {
        // set_cookie()/get_cookie() live in the cookie helper, which is not
        // loaded by default. Without this they are simply undefined and the
        // cookie is never written — the failure is silent.
        helper('cookie');
    }

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $existing = $this->peek();

        if ($existing !== null) {
            return $this->token = $existing;
        }

        $token = bin2hex(random_bytes(32));

        $this->write($token);

        return $this->token = $token;
    }

    /** The token if one exists, without creating one. */
    public function peek(): ?string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        // The request's cookie, or one this request has just issued — otherwise
        // the save and the read inside a single request disagree.
        $raw = (string) (service('request')->getCookie(self::COOKIE) ?? '');

        if ($raw === '' && isset($_COOKIE[self::COOKIE])) {
            $raw = (string) $_COOKIE[self::COOKIE];
        }

        // Exactly 64 hex characters, or it did not come from here.
        return preg_match('/^[a-f0-9]{64}$/', $raw) === 1 ? $this->token = $raw : null;
    }

    /**
     * Issue a fresh token, discarding the old one.
     *
     * Called after signing in. The guest basket has already been merged into the
     * account by then, so the old token points at nothing — and rotating means a
     * token someone else may have seen cannot be replayed against the account.
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->write($token);

        return $this->token = $token;
    }

    /** Forget the visitor entirely. */
    public function forget(): void
    {
        $this->token = null;

        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[self::COOKIE]);
    }

    private function write(string $token): void
    {
        /*
         * PHP's own setcookie(), not the framework's.
         *
         * CI4 keeps cookies on the shared Response, but redirect() returns a
         * NEW RedirectResponse and only carries them over if the caller
         * remembers ->withCookies(). Every wishlist action ends in a redirect,
         * so the cookie was written and then thrown away — the row saved, and
         * the next request could not find it again.
         *
         * Emitting it directly means it cannot be lost by a response object
         * being swapped, and no caller has to remember anything.
         */
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + self::LIFETIME,
            'path'     => '/',
            'secure'   => service('request')->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Readable within THIS request too, so a save and a read in the same
        // request agree about who the visitor is.
        $_COOKIE[self::COOKIE] = $token;
    }
}
