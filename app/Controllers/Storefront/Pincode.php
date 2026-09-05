<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use CodeIgniter\Controller;

/**
 * Look up an Indian PIN code.
 *
 * WHY THIS IS A PROXY AND NOT A DIRECT CALL FROM THE BROWSER
 *
 *  - Caching. The same few hundred PIN codes get typed over and over; going out
 *    to the network for each is slow for the customer and rude to the service.
 *  - The upstream can change or disappear. One place to swap it beats editing
 *    JavaScript scattered across three forms.
 *  - No third party learns a customer's address before they have ordered.
 *
 * India Post's service is free and needs no key. If it is unreachable the
 * response says so plainly and the form falls back to typed entry — a checkout
 * must never be blocked by a lookup.
 */
class Pincode extends Controller
{
    private const UPSTREAM = 'https://api.postalpincode.in/pincode/';

    /** A month. Post offices do not move often. */
    private const TTL = 60 * 60 * 24 * 30;

    public function show(string $code)
    {
        // Exactly six digits, and never starting at zero — that is the whole
        // shape of an Indian PIN, and it keeps anything else out of the URL we
        // build below.
        if (preg_match('/^[1-9][0-9]{5}$/', $code) !== 1) {
            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => 'That is not a six-digit PIN code.']);
        }

        $key    = 'pin_' . $code;
        $cached = cache($key);

        if ($cached !== null) {
            return $this->response->setJSON($cached);
        }

        $payload = $this->lookup($code);

        // Only a real answer is cached. Caching a network failure for a month
        // would turn a blip into a lasting outage for that PIN.
        if ($payload['ok']) {
            cache()->save($key, $payload, self::TTL);
        }

        return $this->response->setJSON($payload);
    }

    /** @return array<string, mixed> */
    private function lookup(string $code): array
    {
        $ch = curl_init(self::UPSTREAM . $code);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            // Short: a customer is watching a spinner. Better to let them type
            // it themselves than to hold the form.
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Rasmein/1.0',
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if (! is_string($body) || $body === '') {
            log_message('warning', 'PIN lookup failed for {c}: {e}', ['c' => $code, 'e' => $err]);

            return ['ok' => false, 'error' => 'Could not check that PIN code just now.'];
        }

        $data = json_decode($body, true);
        $post = $data[0] ?? null;

        if (! is_array($post) || ($post['Status'] ?? '') !== 'Success' || empty($post['PostOffice'])) {
            return ['ok' => false, 'error' => 'We do not recognise that PIN code.'];
        }

        $offices = $post['PostOffice'];
        $first   = $offices[0];

        /*
         * District, not Name. `Name` is the individual post office — "Amer
         * Fort" — where the customer expects to see their city. District is the
         * closest thing India Post gives to that.
         */
        return [
            'ok'    => true,
            'state' => (string) ($first['State'] ?? ''),
            'city'  => (string) ($first['District'] ?? ''),
            // Every locality on that PIN, so a customer can pick the right one
            // rather than accept whichever came back first.
            'areas' => array_values(array_unique(array_filter(array_map(
                static fn (array $o): string => (string) ($o['Name'] ?? ''),
                $offices
            )))),
        ];
    }
}
