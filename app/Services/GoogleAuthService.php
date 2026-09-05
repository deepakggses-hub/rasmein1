<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sign in with Google, for customers.
 *
 * Separate from GoogleMailService, which authorises the SHOP to send mail. This
 * authorises a VISITOR to be themselves. Same provider, different consent,
 * different credentials — sharing one client between them would mean a token
 * scoped to send mail could also be used to identify people.
 *
 * SECURITY
 *
 *  - `state` is random per attempt, kept in the session, and compared on the
 *    way back. Without it, an attacker can hand someone a callback URL carrying
 *    their own code and silently attach the victim's session to their account.
 *  - The account is matched on Google's `sub`, not the email. An email can
 *    change hands; a subject id cannot. Matching on email alone would let
 *    whoever later controls an address inherit an existing account.
 *  - `email_verified` from Google is REQUIRED. Google will hand back
 *    unverified addresses for some account types, and treating one as proof of
 *    ownership would let someone claim an address they do not hold.
 */
class GoogleAuthService
{
    private const AUTH   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN  = 'https://oauth2.googleapis.com/token';
    private const PROFILE = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /** Where to send someone to sign in. */
    public function authUrl(): string
    {
        $state = bin2hex(random_bytes(24));

        session()->set('google_oauth_state', $state);

        return self::AUTH . '?' . http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            // Ask every time rather than silently reusing a session: on a shared
            // computer the previous person's account should not be offered.
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * Exchange the callback for a profile.
     *
     * @return array{ok: bool, error: ?string, profile: array<string, mixed>}
     */
    public function handleCallback(string $code, string $state): array
    {
        $expected = (string) session()->get('google_oauth_state');
        session()->remove('google_oauth_state');

        // hash_equals, not ===: a timing difference here is small but free to
        // avoid.
        if ($expected === '' || ! hash_equals($expected, $state)) {
            return ['ok' => false, 'error' => 'That sign-in link has expired. Please try again.', 'profile' => []];
        }

        $token = $this->post(self::TOKEN, [
            'code'          => $code,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        if (! isset($token['access_token'])) {
            log_message('error', 'Google token exchange failed: {r}', ['r' => json_encode($token)]);

            return ['ok' => false, 'error' => 'Google did not complete the sign-in. Please try again.', 'profile' => []];
        }

        $profile = $this->get(self::PROFILE, (string) $token['access_token']);

        if (! isset($profile['sub'], $profile['email'])) {
            return ['ok' => false, 'error' => 'Google did not share an email address.', 'profile' => []];
        }

        /*
         * Google returns email_verified as a bool or the string "true"
         * depending on the endpoint. Anything other than a clear yes is treated
         * as unverified — an address nobody has proved they own must not become
         * an account.
         */
        $verified = $profile['email_verified'] ?? false;

        if ($verified !== true && $verified !== 'true') {
            return [
                'ok'      => false,
                'error'   => 'That Google account has an unconfirmed email address.',
                'profile' => [],
            ];
        }

        return [
            'ok'      => true,
            'error'   => null,
            'profile' => [
                'google_id' => (string) $profile['sub'],
                'email'     => strtolower((string) $profile['email']),
                'name'      => trim((string) ($profile['name'] ?? '')),
                'avatar'    => (string) ($profile['picture'] ?? ''),
            ],
        ];
    }

    public function redirectUri(): string
    {
        return site_url('account/google/callback');
    }

    // -----------------------------------------------------------------

    private function clientId(): string
    {
        return trim((string) service('settings')->get('google_auth_client_id', ''));
    }

    private function clientSecret(): string
    {
        $raw = (string) service('settings')->get('google_auth_client_secret', '');

        if ($raw === '') {
            return '';
        }

        // Stored encrypted, like every other secret in this application.
        try {
            return service('encrypter')->decrypt(base64_decode($raw, true) ?: '');
        } catch (\Throwable $e) {
            log_message('error', 'Google auth secret could not be decrypted.');

            return '';
        }
    }

    /** @return array<string, mixed> */
    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_TIMEOUT        => 15,
            // Never disable this. An unverified TLS connection to an identity
            // provider is not an identity provider.
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return is_string($body) ? (array) json_decode($body, true) : [];
    }

    /** @return array<string, mixed> */
    private function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return is_string($body) ? (array) json_decode($body, true) : [];
    }
}
