<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Everything about the Gmail transport that can be checked WITHOUT talking to
 * Google: the consent URL, state handling, credential encryption, and the MIME
 * message. The live handshake needs real credentials and a browser.
 */
class DiagGmail extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-gmail';
    protected $description = 'Check the Gmail OAuth transport, short of contacting Google.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        CLI::newLine();
        $g = service('googleMail');
        $settings = service('settings');

        $this->section('Before anything is configured');
        $this->check('not configured', ! $g->isConfigured());
        $this->check('not connected', ! $g->isConnected());
        $this->check('redirect URI is derived from the site', str_contains($g->redirectUri(), '/admin/mail/google/callback'), $g->redirectUri());

        $this->section('Credentials are encrypted at rest');
        $settings->set('mail_google_client_id', '123456-abc.apps.googleusercontent.com');
        $settings->set('mail_google_client_secret', base64_encode(service('encrypter')->encrypt('GOCSPX-thisIsTheSecret')));
        $settings->set('mail_google_refresh_token', base64_encode(service('encrypter')->encrypt('1//refresh-token-value')));
        $settings->flush();

        $stored = db_connect()->table('settings')->select('value')
            ->where('key_name', 'mail_google_client_secret')->get()->getRowArray()['value'] ?? '';
        $storedToken = db_connect()->table('settings')->select('value')
            ->where('key_name', 'mail_google_refresh_token')->get()->getRowArray()['value'] ?? '';

        $this->check('secret not readable in the database', ! str_contains($stored, 'GOCSPX'), strlen($stored) . ' chars of ciphertext');
        $this->check('refresh token not readable either', ! str_contains($storedToken, 'refresh-token-value'));

        // A fresh instance, because the service caches settings per request.
        $fresh = new \App\Services\GoogleMailService();
        $this->check('secret decrypts correctly', $fresh->clientSecret() === 'GOCSPX-thisIsTheSecret');
        $this->check('refresh token decrypts correctly', $fresh->refreshToken() === '1//refresh-token-value');
        $this->check('now reports configured', $fresh->isConfigured());
        $this->check('now reports connected', $fresh->isConnected());

        $this->section('The consent URL');
        $url = $fresh->authorisationUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->check('points at Google', str_starts_with($url, 'https://accounts.google.com/'));
        $this->check('carries the client id', ($query['client_id'] ?? '') === '123456-abc.apps.googleusercontent.com');
        $this->check('asks for offline access', ($query['access_type'] ?? '') === 'offline');
        $this->check('forces consent, so a refresh token is returned', ($query['prompt'] ?? '') === 'consent');
        $this->check(
            'requests send-only scope',
            str_contains((string) ($query['scope'] ?? ''), 'gmail.send')
                && ! str_contains((string) ($query['scope'] ?? ''), 'mail.google.com'),
            (string) ($query['scope'] ?? '')
        );
        $this->check('includes a state value', strlen((string) ($query['state'] ?? '')) >= 32);
        $this->check('state was remembered in the session', session('google_oauth_state') === ($query['state'] ?? null));

        $this->section('State is enforced on the way back');
        $result = $fresh->completeAuthorisation('fake-code', 'wrong-state');
        $this->check('a mismatched state is refused', ! $result['ok'], (string) $result['error']);
        $this->check('and the stored state is cleared', session('google_oauth_state') === null);

        $this->section('The MIME message');
        $method = new \ReflectionMethod($fresh, 'buildMime');
        $method->setAccessible(true);
        $raw = (string) $method->invoke(
            $fresh,
            'asha@example.test',
            'Your order — दार्जिलिंग',
            '<p>Hello <strong>Asha</strong></p>',
            'Hello Asha',
            'orders@rasmein.com',
            'Rasmein Gifting'
        );

        $this->check('has a From header', str_contains($raw, 'From: Rasmein Gifting <orders@rasmein.com>'));
        $this->check('has a To header', str_contains($raw, 'To: asha@example.test'));
        $this->check('non-ASCII subject is RFC 2047 encoded', str_contains($raw, '=?UTF-8?B?'));
        $this->check('is multipart/alternative', str_contains($raw, 'multipart/alternative'));
        $this->check('carries a plain-text part', str_contains($raw, 'text/plain; charset=UTF-8'));
        $this->check('carries an HTML part', str_contains($raw, 'text/html; charset=UTF-8'));
        $this->check('has a Message-ID on the sending domain', str_contains($raw, '@rasmein.com>'));

        // Decode a part back to prove the body actually survives.
        preg_match_all('/Content-Transfer-Encoding: base64\r\n\r\n(.*?)(?=\r\n--)/s', $raw, $parts);
        $decoded = implode(' ', array_map(static fn (string $p): string => (string) base64_decode(str_replace(["\r", "\n"], '', $p), true), $parts[1] ?? []));
        $this->check('HTML body survives encoding', str_contains($decoded, '<strong>Asha</strong>'));

        $b64 = new \ReflectionMethod($fresh, 'base64Url');
        $b64->setAccessible(true);
        $encoded = (string) $b64->invoke($fresh, $raw);
        $this->check(
            'base64url has no +, / or padding',
            ! str_contains($encoded, '+') && ! str_contains($encoded, '/') && ! str_contains($encoded, '=')
        );

        $this->section('Disconnecting');
        $fresh->disconnect();
        $after = new \App\Services\GoogleMailService();
        $this->check('refresh token is gone', $after->refreshToken() === '');
        $this->check('reports disconnected', ! $after->isConnected());

        // Leave nothing behind.
        foreach (['mail_google_client_id', 'mail_google_client_secret', 'mail_google_refresh_token', 'mail_google_account', 'mail_google_connected_at'] as $key) {
            $settings->set($key, '');
        }
        $settings->flush();

        CLI::newLine();
        CLI::write(sprintf('  %d passed, %d failed', $this->pass, $this->fail), $this->fail === 0 ? 'green' : 'red');
        CLI::write('  The live Google handshake needs real credentials and a browser — it cannot be checked here.', 'yellow');
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function section(string $t): void
    {
        CLI::write('  ' . $t, 'white');
        CLI::write('  ' . str_repeat('-', 60), 'dark_gray');
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->pass++ : $this->fail++;
        CLI::write(sprintf('  [%s] %-44s %s', $ok ? ' ok ' : 'FAIL', $label, mb_substr($detail, 0, 40)), $ok ? 'green' : 'red');
    }
}
