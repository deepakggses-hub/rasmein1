<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailTemplateModel;
use Config\Rasmein;
use Throwable;

/**
 * Renders editable templates and delivers them.
 *
 * TWO THINGS WORTH UNDERSTANDING.
 *
 * 1. Rendering is an ALLOWLIST substitution, not a template engine. Staff can
 *    edit the body, so the body is untrusted-ish input; running it through
 *    anything that evaluates code would turn "edit the welcome email" into
 *    "execute arbitrary PHP". Only the tokens a template declares are replaced,
 *    everything else is left alone, and every substituted VALUE is HTML-escaped
 *    — a customer called `<script>` must not become a script tag in an email.
 *
 * 2. Sending is QUEUED, never inline. An order must not fail because a mail
 *    server is slow, and a customer must not wait on SMTP. Everything lands in
 *    notification_log and `php spark rasmein:send-mail` drains it with capped
 *    retries and exponential backoff.
 */
class MailService
{
    /** Give up after this many tries and leave the row as failed for a human. */
    private const MAX_ATTEMPTS = 5;

    /**
     * Queue an email built from a template.
     *
     * @param array<string, mixed> $data Token values
     *
     * @return bool False when the template is missing or switched off.
     */
    public function queue(string $templateKey, string $recipient, array $data = [], ?int $relatedId = null, string $relatedType = 'order'): bool
    {
        $template = model(EmailTemplateModel::class)->findByKey($templateKey);

        if ($template === null) {
            log_message('error', 'Email template "{key}" does not exist', ['key' => $templateKey]);

            return false;
        }

        if ((int) $template['is_active'] !== 1) {
            log_message('info', 'Email template "{key}" is switched off; nothing queued', ['key' => $templateKey]);

            return false;
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            log_message('error', 'Refused to queue mail to an invalid address for {key}', ['key' => $templateKey]);

            return false;
        }

        $rendered = $this->render($template, $data);

        db_connect()->table('notification_log')->insert([
            'channel'         => 'email',
            'event'           => $templateKey,
            'recipient'       => $recipient,
            'subject'         => $rendered['subject'],
            'template'        => $templateKey,
            'template_key'    => $templateKey,
            'body_html'       => $rendered['body'],
            'payload'         => json_encode($this->redact($data), JSON_UNESCAPED_UNICODE),
            'related_type'    => $relatedType,
            'related_id'      => $relatedId,
            'status'          => 'queued',
            'attempts'        => 0,
            'next_attempt_at' => date('Y-m-d H:i:s'),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Substitute a template's declared tokens.
     *
     * @param array<string, mixed> $template
     * @param array<string, mixed> $data
     *
     * @return array{subject: string, body: string}
     */
    public function render(array $template, array $data): array
    {
        $allowed = model(EmailTemplateModel::class)->placeholdersFor($template);
        $brand   = config(Rasmein::class);

        // Always available, so every template can carry the shop's identity
        // without each caller having to pass it. Kept SEPARATE from the caller's
        // data — see the second substitution pass below for why.
        $global = [
            'brand_name'    => $brand->brandName,
            'brand_tagline' => $brand->brandTagline,
            'support_email' => $brand->supportEmail,
            'support_phone' => $brand->supportPhone,
            'site_url'      => rtrim(base_url(), '/'),
            'year'          => date('Y'),
        ];

        $values = array_merge($global, $data);

        $subject = (string) $template['subject'];
        $body    = (string) ($template['body_html'] ?? '');

        foreach (array_keys($allowed) + [] as $token) {
            $needle = '{{' . $token . '}}';

            if (! array_key_exists($token, $values)) {
                // Declared but not supplied: blank it rather than leaving the
                // raw {{token}} visible to a customer.
                $subject = str_replace($needle, '', $subject);
                $body    = str_replace($needle, '', $body);

                continue;
            }

            $raw = $this->stringify($values[$token]);

            // The subject is plain text; the body is HTML. Escape accordingly.
            $subject = str_replace($needle, $raw, $subject);
            $body    = str_replace($needle, esc($raw), $body);
        }

        // Second pass: the GLOBAL tokens only, so {{brand_name}} works in every
        // template without being declared.
        //
        // This deliberately iterates $global and not $values. Iterating the
        // caller's data would substitute any key it happened to pass, declared
        // or not — which would make the "allowlist" above decorative. A test
        // asserts an undeclared token stays unsubstituted.
        foreach ($global as $token => $value) {
            if (isset($allowed[$token])) {
                continue;
            }

            $needle  = '{{' . $token . '}}';
            $raw     = $this->stringify($value);
            $subject = str_replace($needle, $raw, $subject);
            $body    = str_replace($needle, esc($raw), $body);
        }

        // Anything still wearing {{ }} was never declared. Strip it rather than
        // mailing a customer a placeholder.
        $subject = (string) preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $subject);
        $body    = (string) preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $body);

        return ['subject' => trim($subject), 'body' => $body];
    }

    /**
     * Send everything that is due. Called by the scheduler.
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function drainQueue(int $limit = 50): array
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $rows = $db->table('notification_log')
            ->where('channel', 'email')
            ->where('status', 'queued')
            ->where('attempts <', self::MAX_ATTEMPTS)
            ->groupStart()->where('next_attempt_at', null)->orWhere('next_attempt_at <=', $now)->groupEnd()
            ->orderBy('id', 'ASC')
            ->get($limit)->getResultArray();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $attempts = (int) $row['attempts'] + 1;

            try {
                $this->deliver((string) $row['recipient'], (string) $row['subject'], (string) $row['body_html']);

                $db->table('notification_log')->where('id', $row['id'])->update([
                    'status'   => 'sent',
                    'attempts' => $attempts,
                    'sent_at'  => date('Y-m-d H:i:s'),
                    'error'    => null,
                ]);

                $sent++;
            } catch (Throwable $e) {
                $giveUp = $attempts >= self::MAX_ATTEMPTS;

                // Exponential backoff: 1, 4, 9, 16 minutes. A mail server having
                // a bad minute should not burn every retry in ten seconds.
                $db->table('notification_log')->where('id', $row['id'])->update([
                    'status'          => $giveUp ? 'failed' : 'queued',
                    'attempts'        => $attempts,
                    'error'           => mb_substr($e->getMessage(), 0, 255),
                    'next_attempt_at' => $giveUp ? null : date('Y-m-d H:i:s', time() + ($attempts ** 2) * 60),
                ]);

                $giveUp ? $failed++ : $skipped++;

                log_message('error', 'Mail attempt {n} to {to} failed: {msg}', [
                    'n' => (string) $attempts, 'to' => $row['recipient'], 'msg' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /** Actually hand the message to CodeIgniter's mailer. */
    public function deliver(string $to, string $subject, string $bodyHtml): void
    {
        $email = service('email', null, false);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($this->wrap($bodyHtml));
        $email->setAltMessage($this->toPlainText($bodyHtml));
        $email->setMailType('html');

        if ($email->send(false) === false) {
            throw new \RuntimeException($email->printDebugger(['headers']) ?: 'The mail server rejected the message.');
        }
    }

    /**
     * Put the body in a shell with the brand around it.
     *
     * Inline styles and a table layout, because email clients in 2026 still
     * behave like browsers from 2003 — no external stylesheet, no flexbox.
     */
    public function wrap(string $bodyHtml): string
    {
        $brand = config(Rasmein::class);
        $name  = esc($brand->brandName);
        $site  = esc(rtrim(base_url(), '/'), 'attr');
        $mail  = esc($brand->supportEmail);

        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
        <body style="margin:0;padding:0;background:#FAF6F3;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF6F3;padding:24px 12px;">
        <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #E3D6CE;">
        <tr><td style="background:#401026;padding:20px 28px;">
        <span style="font-family:Georgia,serif;font-size:22px;font-weight:600;color:#FAF6F3;">{$name}</span>
        </td></tr>
        <tr><td style="padding:28px;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#2C2333;">
        {$bodyHtml}
        </td></tr>
        <tr><td style="border-top:1px solid #E3D6CE;padding:18px 28px;font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#6B6070;">
        <a href="{$site}" style="color:#5E1F3D;">{$site}</a> &middot; {$mail}
        </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;
    }

    /**
     * A readable plain-text alternative, for clients that will not show HTML.
     *
     * ORDER MATTERS HERE, and getting it wrong is subtle. The HTML body holds
     * escaped user content — a customer named `<script>` is stored as
     * `&lt;script&gt;`. Running html_entity_decode() over that turns it back
     * into `<script>` in the text part. A conforming client renders text/plain
     * as text so nothing executes, but the value has still round-tripped back
     * into markup, and anything downstream that puts that text into an HTML
     * context inherits the problem.
     *
     * So: strip real tags, decode entities for readability (&amp; → &), then
     * remove anything the decode reintroduced that still looks like a tag. A
     * plain-text email should contain no angle-bracketed tags at all.
     */
    public function toPlainText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|h2|h3|h4|li|tr)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li[^>]*>#i', '- ', $text) ?? $text;

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Second pass: kill anything that decoding turned back into a tag.
        $text = preg_replace('#</?[a-z][^>]*>#i', '', $text) ?? $text;

        // Collapse the whitespace the tag removal leaves behind.
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return implode(', ', array_map([$this, 'stringify'], $value));
        }

        return (string) $value;
    }

    /** Never let a secret reach the stored payload. */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/pass|token|secret|key|otp/i', (string) $key) === 1) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }
}
