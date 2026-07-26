<?php

declare(strict_types=1);

namespace App\Services;

use Config\Services as AppServices;
use RuntimeException;
use Throwable;

/**
 * Sending through Gmail with OAuth 2.0.
 *
 * WHY THE HTTP API AND NOT SMTP
 *
 * OAuth over SMTP needs the XOAUTH2 mechanism, and CodeIgniter's Email class
 * supports only LOGIN, PLAIN and CRAM-MD5 — there is no hook to add another
 * without reaching into protected internals and re-implementing the SMTP
 * conversation. Google's own REST endpoint takes a Bearer token and a raw
 * RFC 2822 message, which sidesteps the problem entirely. It is also what
 * WordPress's mail plugins do for their Gmail mailer.
 *
 * WHAT IS STORED, AND HOW
 *
 *  - client ID: plain. It is not a secret; it appears in the consent URL.
 *  - client secret: ENCRYPTED.
 *  - refresh token: ENCRYPTED. This is the crown jewel — it grants the ability
 *    to send as that account indefinitely until revoked.
 *  - access token: cache only, never the database. It expires in an hour and
 *    writing it to disk on every send would be pointless churn.
 *
 * The scope requested is `gmail.send` alone. It cannot read, search or delete
 * mail. Asking for less is the difference between a leaked token being an
 * embarrassment and being a catastrophe.
 */
class GoogleMailService
{
    private const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const SEND_ENDPOINT  = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
    private const USERINFO       = 'https://www.googleapis.com/oauth2/v3/userinfo';

    /** Send only. Deliberately not gmail.compose or mail.google.com. */
    private const SCOPES = 'https://www.googleapis.com/auth/gmail.send openid email';

    private const TOKEN_CACHE_KEY = 'google_mail_access_token';

    // =================================================================
    // Configuration state
    // =================================================================

    public function clientId(): string
    {
        return trim((string) $this->raw('mail_google_client_id'));
    }

    public function clientSecret(): string
    {
        return (string) (AppServices::decryptMailPassword((string) $this->raw('mail_google_client_secret')) ?? '');
    }

    public function refreshToken(): string
    {
        return (string) (AppServices::decryptMailPassword((string) $this->raw('mail_google_refresh_token')) ?? '');
    }

    public function account(): string
    {
        return trim((string) $this->raw('mail_google_account'));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && $this->refreshToken() !== '';
    }

    /** The redirect URI that must be registered in Google Cloud Console. */
    public function redirectUri(): string
    {
        return site_url('admin/mail/google/callback');
    }

    // =================================================================
    // The consent handshake
    // =================================================================

    /**
     * Build the consent URL and remember a state value to check on return.
     *
     * `state` is CSRF protection for OAuth: without it, an attacker can hand a
     * victim a crafted callback URL and attach their own Google account to the
     * victim's shop.
     */
    public function authorisationUrl(): string
    {
        $state = bin2hex(random_bytes(24));
        session()->set('google_oauth_state', $state);

        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            // offline + consent together are what actually guarantee a refresh
            // token. Without prompt=consent Google omits it on re-authorisation,
            // and the connection silently stops working in an hour.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ]);
    }

    /**
     * Exchange the one-time code for tokens and store the refresh token.
     *
     * @return array{ok: bool, error: string|null, account: string|null}
     */
    public function completeAuthorisation(string $code, string $state): array
    {
        $expected = (string) session('google_oauth_state');
        session()->remove('google_oauth_state');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            return ['ok' => false, 'error' => 'The authorisation could not be verified. Start again from the mail settings screen.', 'account' => null];
        }

        try {
            $token = $this->post(self::TOKEN_ENDPOINT, [
                'code'          => $code,
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $this->redirectUri(),
                'grant_type'    => 'authorization_code',
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'account' => null];
        }

        if (empty($token['refresh_token'])) {
            return [
                'ok'    => false,
                'error' => 'Google did not return a refresh token. Remove this app from your '
                    . 'Google account permissions and authorise again.',
                'account' => null,
            ];
        }

        $settings = service('settings');
        $settings->set('mail_google_refresh_token', base64_encode(service('encrypter')->encrypt($token['refresh_token'])));

        if (! empty($token['access_token'])) {
            $this->cacheAccessToken((string) $token['access_token'], (int) ($token['expires_in'] ?? 3600));
        }

        $account = $this->fetchAccount((string) ($token['access_token'] ?? ''));

        $settings->set('mail_google_account', $account ?? '');
        $settings->set('mail_google_connected_at', date('Y-m-d H:i:s'));
        $settings->flush();

        return ['ok' => true, 'error' => null, 'account' => $account];
    }

    public function disconnect(): void
    {
        $settings = service('settings');
        $settings->set('mail_google_refresh_token', '');
        $settings->set('mail_google_account', '');
        $settings->set('mail_google_connected_at', '');
        $settings->flush();

        cache()->delete(self::TOKEN_CACHE_KEY);
    }

    // =================================================================
    // Sending
    // =================================================================

    /**
     * Send one message. Throws on failure so the caller's retry logic applies.
     */
    public function send(string $to, string $subject, string $bodyHtml, string $bodyText, string $fromEmail, string $fromName): void
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('No Google account is connected for sending.');
        }

        $raw = $this->buildMime($to, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName);

        $response = $this->post(
            self::SEND_ENDPOINT,
            ['raw' => $this->base64Url($raw)],
            ['Authorization: Bearer ' . $this->accessToken()],
            true
        );

        if (empty($response['id'])) {
            throw new RuntimeException('Gmail accepted the request but returned no message id.');
        }
    }

    /** A cached access token, refreshed when it has expired. */
    private function accessToken(): string
    {
        $cached = cache()->get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->post(self::TOKEN_ENDPOINT, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $this->refreshToken(),
            'grant_type'    => 'refresh_token',
        ]);

        if (empty($token['access_token'])) {
            throw new RuntimeException(
                'Google would not issue an access token. The authorisation may have been '
                . 'revoked — reconnect the account.'
            );
        }

        $this->cacheAccessToken((string) $token['access_token'], (int) ($token['expires_in'] ?? 3600));

        return (string) $token['access_token'];
    }

    private function cacheAccessToken(string $token, int $expiresIn): void
    {
        // Expire early, so a token is never used in the seconds around its
        // actual expiry.
        cache()->save(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 120));
    }

    private function fetchAccount(string $accessToken): ?string
    {
        if ($accessToken === '') {
            return null;
        }

        try {
            $info = $this->get(self::USERINFO, ['Authorization: Bearer ' . $accessToken]);

            return isset($info['email']) ? (string) $info['email'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    // =================================================================
    // Message construction
    // =================================================================

    /**
     * A multipart/alternative RFC 2822 message.
     *
     * Built by hand rather than borrowed from CI4's Email, whose builder is
     * protected and geared to its own SMTP transport. Headers with non-ASCII
     * content are RFC 2047 encoded; bodies are base64 so that long lines and
     * UTF-8 survive intact.
     */
    private function buildMime(string $to, string $subject, string $html, string $text, string $fromEmail, string $fromName): string
    {
        $boundary = 'rsm_' . bin2hex(random_bytes(12));
        $eol      = "\r\n";

        $from = $fromName !== ''
            ? $this->encodeHeader($fromName) . ' <' . $fromEmail . '>'
            : $fromEmail;

        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . ($this->hostFromEmail($fromEmail)) . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $replyTo = trim((string) $this->raw('mail_reply_to'));

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text !== '' ? $text : strip_tags($html)), 76, $eol),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html), 76, $eol),
            '--' . $boundary . '--',
            '',
        ];

        return implode($eol, $headers) . $eol . $eol . implode($eol, $parts);
    }

    private function encodeHeader(string $value): string
    {
        // Pure ASCII needs no encoding, and leaving it alone keeps headers
        // readable in a transcript.
        return preg_match('/[^\x20-\x7E]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private function hostFromEmail(string $email): string
    {
        $parts = explode('@', $email);

        return count($parts) === 2 && $parts[1] !== '' ? $parts[1] : 'localhost';
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    // =================================================================
    // HTTP
    // =================================================================

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $headers
     *
     * @return array<string, mixed>
     */
    private function post(string $url, array $body, array $headers = [], bool $asJson = false): array
    {
        $payload = $asJson ? json_encode($body, JSON_UNESCAPED_SLASHES) : http_build_query($body);
        $headers[] = $asJson ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded';

        return $this->request($url, 'POST', $headers, (string) $payload);
    }

    /** @param list<string> $headers @return array<string, mixed> */
    private function get(string $url, array $headers = []): array
    {
        return $this->request($url, 'GET', $headers, null);
    }

    /** @param list<string> $headers @return array<string, mixed> */
    private function request(string $url, string $method, array $headers, ?string $body): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required to talk to Google.');
        }

        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Certificate verification stays on. Turning it off to "fix" a
            // handshake problem hands the tokens to anyone on the path.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);
        $status   = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error    = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException('Could not reach Google: ' . ($error ?: 'unknown network error'));
        }

        $decoded = json_decode((string) $response, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status >= 400) {
            // Google's error shape is either {error, error_description} for
            // OAuth or {error: {message}} for the API. Surface whichever is
            // present — the message is the useful part.
            $message = $decoded['error_description']
                ?? ($decoded['error']['message'] ?? null)
                ?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : null)
                ?? 'HTTP ' . $status;

            throw new RuntimeException('Google refused the request: ' . $message);
        }

        return $decoded;
    }

    /**
     * Cached mail settings for THIS instance.
     *
     * This began as a `static` inside raw(), which was a mistake: a method
     * static is shared by every instance of the class for the whole request. A
     * newly constructed object inherited the first one's snapshot, so settings
     * saved mid-request were invisible — reading credentials, saving new ones,
     * then re-reading in the same request returned the stale values. An instance
     * property is what per-instance caching actually looks like.
     *
     * @var array<string, string>|null
     */
    private ?array $settingsCache = null;

    /** Read a mail setting raw, so a blank value is not mistaken for absent. */
    private function raw(string $key): string
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = [];

            try {
                foreach (db_connect()->table('settings')->select('key_name, value')
                    ->where('group_name', 'mail')->get()->getResultArray() as $row) {
                    $this->settingsCache[$row['key_name']] = (string) $row['value'];
                }
            } catch (Throwable) {
                $this->settingsCache = [];
            }
        }

        return $this->settingsCache[$key] ?? '';
    }

    /** Drop the cache, for when settings change inside one request. */
    public function refresh(): void
    {
        $this->settingsCache = null;
    }
}
