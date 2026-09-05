<?php

declare(strict_types=1);

namespace App\Services;

/**
 * One-time codes, for signing in and for confirming a new email address.
 *
 * DESIGN NOTES, and why each rule is here
 *
 *  - The code is stored HASHED. A leaked database must not hand anyone a
 *    working set of sign-in codes, and nothing ever needs to read one back.
 *  - Comparison is constant-time via password_verify, so the time taken cannot
 *    narrow down the digits.
 *  - Six digits is 1,000,000 possibilities, which is only safe because guesses
 *    are capped: five wrong attempts burn the code entirely. Without that cap a
 *    six-digit code is guessable in an afternoon.
 *  - Codes live ten minutes. Long enough for a slow mail server, short enough
 *    that a code sitting in an unattended inbox stops working.
 *  - Requesting a new code for the same address is rate-limited, or the endpoint
 *    becomes a way to flood someone's inbox using our mail server.
 *  - A pending signup's details ride along in `payload`, so no half-made account
 *    exists until the code is confirmed. An unverified row in `customers` would
 *    block the real person from registering that address later.
 */
class OtpService
{
    private const LENGTH       = 6;
    private const TTL_MINUTES  = 10;
    private const MAX_ATTEMPTS = 5;

    /** No more than this many codes to one address per hour. */
    private const MAX_PER_HOUR = 5;

    /**
     * Issue a code and email it.
     *
     * @param array<string, mixed> $payload Carried through to verification
     *
     * @return array{ok: bool, error: ?string, expires_at: ?string}
     */
    public function issue(string $email, string $purpose = 'login', array $payload = []): array
    {
        $email = strtolower(trim($email));
        $db    = db_connect();

        $recent = $db->table('auth_codes')
            ->where('email', $email)
            ->where('created_at >', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->countAllResults();

        if ($recent >= self::MAX_PER_HOUR) {
            return [
                'ok'    => false,
                // Deliberately vague about the limit itself.
                'error' => 'Too many codes requested. Please wait a little while before trying again.',
                'expires_at' => null,
            ];
        }

        /*
         * Any earlier unused code for this address and purpose is retired.
         * Two live codes means the older one still works after the person has
         * asked for a replacement, which is exactly what someone who saw the
         * first one wants.
         */
        $db->table('auth_codes')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('consumed_at', null)
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);

        // random_int, not rand(): the latter is predictable from a few samples.
        $code = str_pad((string) random_int(0, 999999), self::LENGTH, '0', STR_PAD_LEFT);

        $expires = date('Y-m-d H:i:s', strtotime('+' . self::TTL_MINUTES . ' minutes'));

        $db->table('auth_codes')->insert([
            'email'      => $email,
            'purpose'    => $purpose,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'payload'    => $payload === [] ? null : json_encode($payload),
            'expires_at' => $expires,
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->send($email, $code, $purpose, $payload);

        return ['ok' => true, 'error' => null, 'expires_at' => $expires];
    }

    /**
     * Check a code.
     *
     * @return array{ok: bool, error: ?string, payload: array<string, mixed>}
     */
    public function verify(string $email, string $code, string $purpose = 'login'): array
    {
        $email = strtolower(trim($email));
        $code  = preg_replace('/\D/', '', $code) ?? '';
        $db    = db_connect();

        $row = $db->table('auth_codes')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('consumed_at', null)
            ->orderBy('id', 'DESC')
            ->get(1)->getRowArray();

        // One message for "no code", "expired" and "wrong code". Distinguishing
        // them tells someone probing which addresses have a code waiting.
        $generic = 'That code is not right, or it has expired. Ask for a new one.';

        if ($row === null) {
            return ['ok' => false, 'error' => $generic, 'payload' => []];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'error' => $generic, 'payload' => []];
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            $db->table('auth_codes')->where('id', $row['id'])
                ->update(['consumed_at' => date('Y-m-d H:i:s')]);

            return [
                'ok'      => false,
                'error'   => 'Too many attempts on that code. Ask for a new one.',
                'payload' => [],
            ];
        }

        if (! password_verify($code, (string) $row['code_hash'])) {
            $db->table('auth_codes')->where('id', $row['id'])
                ->set('attempts', 'attempts + 1', false)->update();

            return ['ok' => false, 'error' => $generic, 'payload' => []];
        }

        // Single use. Burned before the caller does anything with the result,
        // so a slow follow-up cannot be replayed with the same code.
        $db->table('auth_codes')->where('id', $row['id'])
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);

        return [
            'ok'      => true,
            'error'   => null,
            'payload' => $row['payload'] !== null ? (array) json_decode((string) $row['payload'], true) : [],
        ];
    }

    /** Codes that are spent or long past are not worth keeping. */
    public function prune(): int
    {
        $db = db_connect();

        $db->query(
            'DELETE FROM auth_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 2 DAY) '
            . 'OR (consumed_at IS NOT NULL AND consumed_at < DATE_SUB(NOW(), INTERVAL 2 DAY))'
        );

        return $db->affectedRows();
    }

    // -----------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    private function send(string $email, string $code, string $purpose, array $payload): void
    {
        try {
            service('mail')->queue(
                $purpose === 'verify_email' ? 'customer_verify_email' : 'customer_login_code',
                $email,
                [
                    'customer_name' => (string) ($payload['name'] ?? 'there'),
                    'otp_code'      => $code,
                    'otp_minutes'   => (string) self::TTL_MINUTES,
                ]
            );
        } catch (\Throwable $e) {
            /*
             * A failure here must never surface in the response. Whether an
             * address exists — or whether mail to it succeeds — is exactly what
             * someone probing for accounts is trying to learn.
             */
            log_message('error', 'OTP send failed for {e}: {m}', ['e' => $email, 'm' => $e->getMessage()]);
        }
    }
}
