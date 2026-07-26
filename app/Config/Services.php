<?php

declare(strict_types=1);

namespace Config;

use App\Models\SettingModel;
use App\Services\AuditService;
use App\Services\CartService;
use App\Services\GiftBoxBuilderService;
use App\Services\HtmlSanitiser;
use App\Services\CsvExporter;
use App\Services\GoogleMailService;
use App\Services\ImageUploadService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\SettingsService;
use CodeIgniter\Config\BaseService;

/**
 * Application services.
 *
 * Register shared, long-lived collaborators here so controllers and views
 * resolve the same instance instead of newing one up each time.
 */
class Services extends BaseService
{
    /** Runtime admin settings, including the Buy/Enquire master switch. */
    public static function settings(bool $getShared = true): SettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('settings');
        }

        return new SettingsService(model(SettingModel::class));
    }

    /** Recomputes every total from the database. Never trusts the client. */
    public static function pricing(bool $getShared = true): PricingService
    {
        if ($getShared) {
            return static::getSharedInstance('pricing');
        }

        return new PricingService(static::settings());
    }

    /** Owns the database-backed cart. */
    public static function cart(bool $getShared = true): CartService
    {
        if ($getShared) {
            return static::getSharedInstance('cart');
        }

        return new CartService(static::settings(), static::pricing());
    }

    /** The Build-Your-Own-Gift-Box flow. */
    public static function builder(bool $getShared = true): GiftBoxBuilderService
    {
        if ($getShared) {
            return static::getSharedInstance('builder');
        }

        return new GiftBoxBuilderService(static::cart());
    }

    /** Turns a cart into an order, transactionally. */
    public static function orders(bool $getShared = true): OrderService
    {
        if ($getShared) {
            return static::getSharedInstance('orders');
        }

        return new OrderService(static::settings(), static::pricing(), static::cart());
    }

    /** Allowlist HTML sanitiser for staff-authored content. */
    public static function sanitiser(bool $getShared = true): HtmlSanitiser
    {
        if ($getShared) {
            return static::getSharedInstance('sanitiser');
        }

        return new HtmlSanitiser();
    }

    /** Validates, re-encodes and stores uploaded images. */
    public static function images(bool $getShared = true): ImageUploadService
    {
        if ($getShared) {
            return static::getSharedInstance('images');
        }

        return new ImageUploadService();
    }

    /** Streams CSV exports, neutralising spreadsheet formula injection. */
    public static function csv(bool $getShared = true): CsvExporter
    {
        if ($getShared) {
            return static::getSharedInstance('csv');
        }

        return new CsvExporter();
    }

    /**
     * The mailer, configured from the DATABASE rather than only from .env.
     *
     * Overriding the framework's own email service means every existing caller
     * — MailService, the template test-send, anything added later — picks up the
     * settings an administrator saved, without each one having to know about
     * them. Values fall back to Config\Email (and so to .env) when a setting is
     * blank, so a server configured the old way keeps working.
     */
    public static function email($config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('email', $config);
        }

        if ($config === null) {
            $config = static::mailConfig();
        }

        return new \CodeIgniter\Email\Email($config);
    }

    /**
     * Build a Config\Email out of the stored settings.
     *
     * Reads the `mail` settings group with a RAW query rather than through
     * SettingsService::get(). That method treats an empty stored value as
     * absent and hands back the default — which meant choosing "None" for
     * encryption silently stayed on TLS, and clearing the SMTP username brought
     * the .env one back. Here a key that EXISTS is honoured even when its value
     * is an empty string; only a genuinely missing key falls back to
     * Config\Email (and so to .env).
     */
    public static function mailConfig(): \Config\Email
    {
        $config = config(\Config\Email::class);

        try {
            $rows = db_connect()->table('settings')
                ->select('key_name, value')
                ->where('group_name', 'mail')
                ->get()->getResultArray();
        } catch (\Throwable) {
            // Settings table not migrated yet — .env only.
            return $config;
        }

        $stored = [];

        foreach ($rows as $row) {
            $stored[$row['key_name']] = (string) $row['value'];
        }

        // Present-but-blank is a real answer; only absence defers to $fallback.
        $pick = static fn (string $key, $fallback) => array_key_exists($key, $stored)
            ? $stored[$key]
            : $fallback;

        $protocol = $pick('mail_protocol', $config->protocol);

        if (in_array($protocol, ['mail', 'sendmail', 'smtp'], true)) {
            $config->protocol = $protocol;
        }

        // gmail_api is our own transport, not one CodeIgniter knows about. If it
        // were assigned to Config\Email->protocol the framework would fall
        // through to its default and send unauthenticated mail. Leave the
        // framework config on smtp; MailService routes around it.
        if ($protocol === 'gmail_api') {
            $config->protocol = 'smtp';
        }

        // An empty from address would be worse than the .env one, so this single
        // field still prefers a non-empty fallback.
        $from = (string) $pick('mail_from_email', '');
        $config->fromEmail = $from !== '' ? $from : $config->fromEmail;

        $name = (string) $pick('mail_from_name', '');
        $config->fromName = $name !== '' ? $name : $config->fromName;

        $path = (string) $pick('mail_sendmail_path', '');
        $config->mailPath = $path !== '' ? $path : $config->mailPath;

        $config->wordWrap = (string) $pick('mail_word_wrap', $config->wordWrap ? '1' : '0') === '1';

        if ($config->protocol === 'smtp') {
            $config->SMTPHost = (string) $pick('mail_smtp_host', $config->SMTPHost);
            // Honoured even when blank: an SMTP server that wants no
            // authentication must be configurable.
            $config->SMTPUser = (string) $pick('mail_smtp_user', $config->SMTPUser);

            $port = (int) $pick('mail_smtp_port', $config->SMTPPort);
            $config->SMTPPort = $port > 0 ? $port : $config->SMTPPort;

            $timeout = (int) $pick('mail_smtp_timeout', $config->SMTPTimeout);
            $config->SMTPTimeout = $timeout > 0 ? $timeout : $config->SMTPTimeout;

            $method = (string) $pick('mail_smtp_auth_method', $config->SMTPAuthMethod);
            $config->SMTPAuthMethod = $method !== '' ? $method : $config->SMTPAuthMethod;

            // Stored as none|tls|ssl rather than ''|tls|ssl, so "no encryption"
            // is an explicit token that cannot be mistaken for "unset".
            $crypto = (string) $pick('mail_smtp_crypto', $config->SMTPCrypto);
            $config->SMTPCrypto = match ($crypto) {
                'none', '' => '',
                'ssl'      => 'ssl',
                default    => 'tls',
            };

            $password = static::decryptMailPassword((string) $pick('mail_smtp_pass', ''));
            $config->SMTPPass = $password ?? ($config->SMTPUser === '' ? '' : $config->SMTPPass);
        }

        return $config;
    }

    /**
     * Decrypt a stored SMTP password.
     *
     * Returns null on any failure — a missing encryption key, a rotated key, a
     * corrupted value — so the caller falls back to .env rather than sending
     * ciphertext as a password and producing a baffling auth error.
     */
    public static function decryptMailPassword(string $stored): ?string
    {
        if (trim($stored) === '') {
            return null;
        }

        try {
            $raw = base64_decode($stored, true);

            if ($raw === false) {
                return null;
            }

            return static::encrypter()->decrypt($raw);
        } catch (\Throwable $e) {
            log_message('error', 'The stored SMTP password could not be decrypted: {msg}', [
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Gmail over OAuth 2.0, for shops sending through a Google account. */
    public static function googleMail(bool $getShared = true): GoogleMailService
    {
        if ($getShared) {
            return static::getSharedInstance('googleMail');
        }

        return new GoogleMailService();
    }

    /** Renders editable templates and drains the mail queue. */
    public static function mail(bool $getShared = true): MailService
    {
        if ($getShared) {
            return static::getSharedInstance('mail');
        }

        return new MailService();
    }

    /** Decides who hears about what, in-app and by email. */
    public static function notify(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notify');
        }

        return new NotificationService();
    }

    /** Writes the admin audit trail. */
    public static function audit(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('audit');
        }

        return new AuditService();
    }
}
