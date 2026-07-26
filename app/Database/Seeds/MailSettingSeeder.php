<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Mail delivery settings.
 *
 * These live in the database so the shop can change them without editing .env
 * on the server. The SMTP password is the exception in kind, not location: it is
 * stored ENCRYPTED (see MailSettings controller) and never rendered back to the
 * browser.
 *
 * Idempotent — an existing key is left alone, so re-seeding never clobbers
 * working credentials.
 */
class MailSettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['mail_protocol', 'smtp', 'string', 'How mail is sent', 'SMTP is right for almost every host. Sendmail and PHP mail() are fallbacks for servers that offer nothing else.', 1],
            ['mail_from_email', '', 'string', 'Send from address', 'Must be an address the sending domain is allowed to use, or mail will be filed as spam.', 2],
            ['mail_from_name', 'Rasmein', 'string', 'Send from name', 'What recipients see as the sender.', 3],
            ['mail_reply_to', '', 'string', 'Reply-to address', 'Where replies should go. Blank uses the from address.', 4],

            ['mail_smtp_host', '', 'string', 'SMTP host', 'For example smtp.gmail.com or smtp.zoho.in.', 10],
            ['mail_smtp_port', '587', 'int', 'SMTP port', '587 with TLS is the usual choice. 465 needs SSL. 25 is normally blocked.', 11],
            ['mail_smtp_user', '', 'string', 'SMTP username', 'Usually the full email address.', 12],
            ['mail_smtp_pass', '', 'string', 'SMTP password', 'Stored encrypted. Leave blank when saving to keep the current one.', 13],
            ['mail_smtp_crypto', 'tls', 'string', 'Encryption', 'TLS for port 587, SSL for port 465, or none. Stored as a word, never blank — an empty setting is indistinguishable from an unset one.', 14],
            ['mail_smtp_timeout', '10', 'int', 'Timeout (seconds)', 'How long to wait for the mail server before giving up.', 15],
            ['mail_smtp_auth_method', 'login', 'string', 'Authentication', 'Leave as Login unless your provider says otherwise.', 16],

            ['mail_sendmail_path', '/usr/sbin/sendmail', 'string', 'Sendmail path', 'Only used when the method is Sendmail.', 20],

            // --- Google / Gmail API (OAuth 2.0) ---
            ['mail_google_client_id', '', 'string', 'Google client ID', 'From the OAuth client you create in Google Cloud Console.', 40],
            ['mail_google_client_secret', '', 'string', 'Google client secret', 'Stored encrypted. Leave blank when saving to keep the current one.', 41],
            ['mail_google_refresh_token', '', 'string', 'Google refresh token', 'Obtained by authorising the account. Stored encrypted; never entered by hand.', 42],
            ['mail_google_account', '', 'string', 'Authorised Google account', 'The address that authorised sending. Set automatically.', 43],
            ['mail_google_connected_at', '', 'string', 'Authorised on', 'Set automatically when the account is connected.', 44],

            ['mail_word_wrap', '1', 'bool', 'Wrap long lines', 'Keeps plain-text parts readable in older clients.', 30],
            ['mail_last_test_at', '', 'string', 'Last successful test', 'Set automatically when a test email goes through.', 31],
            ['mail_last_error', '', 'string', 'Last failure', 'The most recent delivery error, kept to help diagnose.', 32],
        ];

        $added = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($rows as [$key, $value, $type, $label, $description, $order]) {
            if ($this->db->table('settings')->where('key_name', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('settings')->insert([
                'key_name'    => $key,
                'value'       => $value,
                'value_type'  => $type,
                'group_name'  => 'mail',
                'label'       => $label,
                'description' => $description,
                'is_public'   => 0,
                // Locked against the generic settings form: these are managed
                // by their own screen, which knows to encrypt the password.
                'is_locked'   => 1,
                'sort_order'  => $order,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $added++;
        }

        echo "  Mail settings: {$added} added (" . count($rows) . " defined).\n";
    }
}
