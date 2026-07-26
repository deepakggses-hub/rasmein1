<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Config\Services as AppServices;
use Throwable;

/**
 * Mail delivery configuration.
 *
 * THE PASSWORD IS THE WHOLE DIFFICULTY.
 *
 * An SMTP password grants the ability to send mail as the shop — it is a
 * credential, and a database backup is not a safe place for one in plain text.
 * So:
 *
 *  - it is encrypted with CodeIgniter's encrypter before storage, keyed by
 *    encryption.key in .env, so the database alone does not yield it;
 *  - it is NEVER rendered back to the browser, not even masked-but-present in a
 *    value attribute where "view source" would reveal it;
 *  - leaving the field blank keeps the stored one, which is how you can edit the
 *    host or port without re-typing a credential;
 *  - it is never passed to the audit log.
 *
 * Without an encryption key we refuse to store it at all rather than quietly
 * fall back to plain text.
 */
class MailSettings extends AdminController
{
    private const PROTOCOLS = [
        'smtp'     => 'SMTP — a mail server (recommended)',
        'sendmail' => 'Sendmail — the server’s own binary',
        'mail'     => 'PHP mail() — last resort',
    ];

    public function index()
    {
        if ($denied = $this->deny('settings.view')) {
            return $denied;
        }

        $settings = $this->settings;

        return $this->adminPage('admin/mail/index', [
            'protocols'   => self::PROTOCOLS,
            'protocol'    => (string) $settings->get('mail_protocol', 'smtp'),
            'values'      => [
                'from_email'       => (string) $settings->get('mail_from_email', ''),
                'from_name'        => (string) $settings->get('mail_from_name', ''),
                'reply_to'         => (string) $settings->get('mail_reply_to', ''),
                'smtp_host'        => (string) $settings->get('mail_smtp_host', ''),
                'smtp_port'        => (string) $settings->get('mail_smtp_port', '587'),
                'smtp_user'        => (string) $settings->get('mail_smtp_user', ''),
                'smtp_crypto'      => (string) $settings->get('mail_smtp_crypto', 'tls'),
                'smtp_timeout'     => (string) $settings->get('mail_smtp_timeout', '10'),
                'smtp_auth_method' => (string) $settings->get('mail_smtp_auth_method', 'login'),
                'sendmail_path'    => (string) $settings->get('mail_sendmail_path', '/usr/sbin/sendmail'),
                'word_wrap'        => (bool) $settings->get('mail_word_wrap', true),
            ],
            // Only whether one is stored, never the value itself.
            'hasPassword' => trim((string) $settings->get('mail_smtp_pass', '')) !== '',
            'canEncrypt'  => $this->encryptionAvailable(),
            'lastTest'    => (string) $settings->get('mail_last_test_at', ''),
            'lastError'   => (string) $settings->get('mail_last_error', ''),
            'queue'       => $this->queueSummary(),
            'canManage'   => $this->can('settings.manage'),
        ], 'Mail settings');
    }

    public function save()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $protocol = (string) $this->request->getPost('mail_protocol');

        if (! array_key_exists($protocol, self::PROTOCOLS)) {
            return redirect()->back()->withInput()->with('error', 'That is not a sending method.');
        }

        $fromEmail = trim((string) $this->request->getPost('mail_from_email'));

        if ($fromEmail !== '' && ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'The from address is not a valid email address.');
        }

        $replyTo = trim((string) $this->request->getPost('mail_reply_to'));

        if ($replyTo !== '' && ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'The reply-to address is not a valid email address.');
        }

        $errors = [];

        if ($protocol === 'smtp') {
            if (trim((string) $this->request->getPost('mail_smtp_host')) === '') {
                $errors[] = 'SMTP needs a host.';
            }

            $port = (int) $this->request->getPost('mail_smtp_port');

            if ($port < 1 || $port > 65535) {
                $errors[] = 'The port must be between 1 and 65535.';
            }

            $crypto = (string) $this->request->getPost('mail_smtp_crypto');

            // A common and confusing misconfiguration, so name it rather than
            // letting them discover it as a timeout.
            if ($port === 465 && $crypto !== 'ssl') {
                $errors[] = 'Port 465 expects SSL, not ' . ($crypto === 'none' ? 'no encryption' : strtoupper($crypto)) . '.';
            }

            if ($port === 587 && $crypto === 'ssl') {
                $errors[] = 'Port 587 expects TLS, not SSL.';
            }
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $settings = $this->settings;

        $plain = [
            'mail_protocol'         => $protocol,
            'mail_from_email'       => $fromEmail,
            'mail_from_name'        => trim((string) $this->request->getPost('mail_from_name')),
            'mail_reply_to'         => $replyTo,
            'mail_smtp_host'        => trim((string) $this->request->getPost('mail_smtp_host')),
            'mail_smtp_port'        => (string) (int) $this->request->getPost('mail_smtp_port'),
            'mail_smtp_user'        => trim((string) $this->request->getPost('mail_smtp_user')),
            // 'none' rather than '': an empty string is indistinguishable from
            // "not set" once it is in the settings table.
            'mail_smtp_crypto'      => in_array((string) $this->request->getPost('mail_smtp_crypto'), ['none', 'tls', 'ssl'], true)
                ? (string) $this->request->getPost('mail_smtp_crypto') : 'tls',
            'mail_smtp_timeout'     => (string) max(1, min(120, (int) $this->request->getPost('mail_smtp_timeout'))),
            'mail_smtp_auth_method' => in_array((string) $this->request->getPost('mail_smtp_auth_method'), ['login', 'plain', 'cram-md5'], true)
                ? (string) $this->request->getPost('mail_smtp_auth_method') : 'login',
            'mail_sendmail_path'    => trim((string) $this->request->getPost('mail_sendmail_path')) ?: '/usr/sbin/sendmail',
            'mail_word_wrap'        => $this->request->getPost('mail_word_wrap') !== null ? '1' : '0',
        ];

        foreach ($plain as $key => $value) {
            $settings->set($key, $value);
        }

        // ---- the password, handled apart from everything else ----
        $newPassword = (string) $this->request->getPost('mail_smtp_pass');
        $passwordNote = '';

        if ($this->request->getPost('clear_password') !== null) {
            $settings->set('mail_smtp_pass', '');
            $passwordNote = ' The stored password was cleared.';
        } elseif ($newPassword !== '') {
            if (! $this->encryptionAvailable()) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'The password cannot be stored safely because no encryption key is set. '
                        . 'Run: php spark key:generate — then save again. Other settings were not changed.'
                );
            }

            try {
                $settings->set(
                    'mail_smtp_pass',
                    base64_encode(service('encrypter')->encrypt($newPassword))
                );
                $passwordNote = ' The password was encrypted and stored.';
            } catch (Throwable $e) {
                log_message('error', 'SMTP password could not be encrypted: {msg}', ['msg' => $e->getMessage()]);

                return redirect()->back()->with('error', 'The password could not be encrypted. See the log.');
            }
        }

        $settings->flush();

        // The password is deliberately absent from what is audited.
        service('audit')->log(
            'mail_settings_saved',
            'settings',
            'setting',
            null,
            'Mail set to ' . $protocol,
            [],
            array_diff_key($plain, ['mail_smtp_pass' => null])
        );

        return redirect()->to(site_url('admin/mail'))
            ->with('success', 'Mail settings saved.' . $passwordNote . ' Send a test to confirm they work.');
    }

    /**
     * Send a test to the signed-in administrator.
     *
     * Only ever to your own address — a free-text recipient box on a mail
     * configuration screen is an open relay with a nicer interface.
     */
    public function test()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $to = (string) ($this->admin['email'] ?? '');

        if ($to === '') {
            return redirect()->back()->with('error', 'Your own account has no email address to send to.');
        }

        if (service('throttler')->check('mailtest_' . (int) session('admin_id'), 6, MINUTE) === false) {
            return redirect()->back()->with('error', 'Too many tests. Wait a moment and try again.');
        }

        $config = AppServices::mailConfig();

        if ($config->protocol === 'smtp' && trim($config->SMTPHost) === '') {
            return redirect()->back()->with('error', 'Set an SMTP host before testing.');
        }

        if (trim($config->fromEmail) === '') {
            return redirect()->back()->with('error', 'Set a from address before testing — most servers reject mail without one.');
        }

        $body = '<p>This is a test from your Rasmein admin panel.</p>'
            . '<p>If you are reading it, mail is configured correctly and order '
            . 'confirmations, enquiry alerts and password resets will go out.</p>'
            . '<ul>'
            . '<li>Method: ' . esc($config->protocol) . '</li>'
            . ($config->protocol === 'smtp'
                ? '<li>Host: ' . esc($config->SMTPHost) . ':' . $config->SMTPPort
                    . ' (' . ($config->SMTPCrypto === '' ? 'no encryption' : esc($config->SMTPCrypto)) . ')</li>'
                : '')
            . '<li>Sent: ' . esc(date('j M Y, H:i')) . '</li>'
            . '</ul>';

        try {
            // A fresh, unshared mailer, so a previously cached bad config is not
            // what gets tested.
            $email = AppServices::email($config, false);
            $email->setFrom($config->fromEmail, $config->fromName);
            $email->setTo($to);
            $email->setSubject('Rasmein mail test — ' . date('H:i'));
            $email->setMessage(service('mail')->wrap($body));
            $email->setAltMessage(service('mail')->toPlainText($body));
            $email->setMailType('html');

            if ($email->send(false) === false) {
                throw new \RuntimeException($this->explainFailure($email->printDebugger(['headers'])));
            }
        } catch (Throwable $e) {
            // The real error is the useful part of a failed mail test, so keep it
            // and show it rather than a generic apology.
            $reason = trim(strip_tags($e->getMessage()));
            $this->settings->set('mail_last_error', mb_substr($reason, 0, 500));
            $this->settings->flush();

            log_message('error', 'Mail test failed: {msg}', ['msg' => $reason]);

            return redirect()->back()->with(
                'error',
                'The test did not send. ' . mb_substr($reason, 0, 300)
            );
        }

        $this->settings->set('mail_last_test_at', date('Y-m-d H:i:s'));
        $this->settings->set('mail_last_error', '');
        $this->settings->flush();

        service('audit')->log('mail_test_sent', 'settings', 'setting', null, 'Test sent to ' . $to);

        return redirect()->back()->with('success', 'Test sent to ' . $to . '. Check that inbox, and the spam folder.');
    }

    /** Drain the queue by hand, for when cron is not yet set up. */
    public function drain()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $result = service('mail')->drainQueue(50);

        return redirect()->back()->with(
            $result['failed'] > 0 ? 'error' : 'success',
            sprintf(
                '%d sent, %d waiting to retry, %d gave up.',
                $result['sent'],
                $result['skipped'],
                $result['failed']
            )
        );
    }

    /**
     * Pull the useful line out of a failed SMTP conversation.
     *
     * printDebugger() returns the whole transcript, which OPENS with the
     * server's "220 ready" greeting. Truncating that from the front shows a
     * success message for a failed send — which is exactly what it did the first
     * time this was tested. The informative part is the last non-2xx/3xx
     * response, so find that instead.
     */
    private function explainFailure(string $debug): string
    {
        $text = trim(html_entity_decode(strip_tags($debug), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($text === '') {
            return 'The mail server refused the message without saying why.';
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $problems = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // SMTP replies in the 4xx and 5xx ranges are the failures.
            if (preg_match('/^[45]\d\d[ \-]/', $line) === 1) {
                $problems[] = $line;

                continue;
            }

            // CodeIgniter's own diagnostics.
            if (stripos($line, 'unable to') !== false
                || stripos($line, 'failed') !== false
                || stripos($line, 'could not') !== false
                || stripos($line, 'timed out') !== false
                || stripos($line, 'authentication') !== false) {
                $problems[] = $line;
            }
        }

        if ($problems === []) {
            // Nothing recognisable: the END of the transcript is where a failure
            // sits, so show that rather than the greeting at the start.
            return mb_substr($text, max(0, mb_strlen($text) - 300));
        }

        // Last problem first — it is usually the decisive one.
        return implode(' · ', array_slice(array_reverse(array_unique($problems)), 0, 3));
    }

    private function encryptionAvailable(): bool
    {
        if (trim((string) env('encryption.key', '')) === '') {
            return false;
        }

        try {
            service('encrypter');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, int> */
    private function queueSummary(): array
    {
        $db  = db_connect();
        $out = ['queued' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($db->table('notification_log')
            ->select('status, COUNT(*) AS n', false)
            ->where('channel', 'email')
            ->groupBy('status')->get()->getResultArray() as $row) {
            if (isset($out[$row['status']])) {
                $out[$row['status']] = (int) $row['n'];
            }
        }

        return $out;
    }
}
