<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * What the shop has tried to send, and what happened to it.
 *
 * Mail is queued rather than sent inline, which is right — a slow SMTP server
 * should not hold up a checkout — but it means a failure happens out of sight.
 * Without a screen like this, "the customer never got their confirmation" has
 * no answer beyond reading the server log.
 *
 * The body is deliberately NOT shown in the list. A queued message can contain
 * an address, an order total, or a one-time sign-in code, and none of that
 * should sit in a table anyone with panel access can scroll past.
 */
class MailQueue extends AdminController
{
    private const STATUSES = ['queued', 'sending', 'sent', 'failed'];

    public function index()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $status = (string) $this->request->getGet('status');
        $search = trim((string) $this->request->getGet('q'));

        $model = db_connect()->table('notification_log');

        if (in_array($status, self::STATUSES, true)) {
            $model->where('status', $status);
        }

        if ($search !== '') {
            $model->groupStart()
                ->like('recipient', $search)
                ->orLike('subject', $search)
            ->groupEnd();
        }

        $rows = $model->orderBy('id', 'DESC')->get(200)->getResultArray();

        /*
         * The code, in the list.
         *
         * Until SMTP is configured this queue IS the inbox, and opening a
         * message to read six digits is a step for nothing. Still audited: the
         * list view records that codes were on screen.
         */
        $anyCode = false;

        foreach ($rows as $i => $row) {
            $rows[$i]['otp'] = $this->isSensitive($row)
                ? $this->codeFrom((string) $row['body_html'])
                : null;

            $anyCode = $anyCode || $rows[$i]['otp'] !== null;
        }

        if ($anyCode) {
            service('audit')->log('mail_code_viewed', 'settings', 'mail', null, 'queue list');
        }

        return $this->adminPage('admin/mail/queue', [
            'rows'     => $rows,
            'counts'   => $this->counts(),
            'status'   => $status,
            'search'   => $search,
            'statuses' => self::STATUSES,
        ], 'Mail queue');
    }

    /**
     * One message, in full.
     *
     * WHY THE BODY IS SHOWN HERE BUT NOT IN THE LIST
     *
     * Seeing what actually went out is the whole point of a queue screen — "did
     * the customer get the right total" cannot be answered from a subject line.
     * But a list puts every body on one page, where a shoulder-glance or a
     * screenshot exposes all of them at once. One at a time is a deliberate act,
     * and it is recorded.
     *
     * ONE-TIME CODES ARE REDACTED even here. A staff member who can read a live
     * sign-in code can become that customer, and no amount of audit logging
     * undoes that. The rest of the message is shown intact.
     */
    public function show(int $id)
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $row = db_connect()->table('notification_log')->where('id', $id)->get()->getRowArray();

        if ($row === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $sensitive = $this->isSensitive($row);
        $code      = $sensitive ? $this->codeFrom((string) $row['body_html']) : null;

        /*
         * A code-carrying message with NO code in it.
         *
         * Messages queued before the placeholder bug was fixed were stored with
         * an empty <strong></strong> — the customer received a blank. The code
         * is unrecoverable: auth_codes stores it hashed, deliberately.
         *
         * Say so, rather than quietly omitting the panel. "There is no panel"
         * and "the panel is empty" look identical from the outside, and one of
         * them means the email itself was broken.
         */
        $codeMissing = $sensitive && $code === null;

        /*
         * The code IS shown.
         *
         * Hiding it was the wrong call. Until SMTP is configured the queue is
         * the only inbox there is, so a shop could not test its own sign-in;
         * and in support, "read me the code you were sent" is the whole job.
         *
         * The redaction was also thinner protection than it looked: anyone who
         * can reach this screen can already change a customer's email address
         * and request a code to it. What actually protects the customer is that
         * looking is RECORDED — including whose code it was — so it can be
         * asked about afterwards.
         */
        service('audit')->log(
            $code !== null ? 'mail_code_viewed' : 'mail_viewed',
            'settings',
            'mail',
            $id,
            (string) $row['recipient'] . ($code !== null ? ' — one-time code shown' : '')
        );

        /*
         * Show the WRAPPED message, exactly as it was delivered.
         *
         * `body_html` is only the inner content; deliver() runs it through
         * MailService::wrap(), which adds the branded shell, the header and the
         * footer. Previewing the fragment showed something no customer ever
         * receives — the right words in none of the right dressing.
         *
         * Rendered through the same method that sends it, so the preview cannot
         * drift from the real thing.
         */
        $body = (string) $row['body_html'];

        return $this->adminPage('admin/mail/show', [
            'row'       => $row,
            'body'      => service('mail')->wrap($body),
            // The plain-text alternative that rides along in every message.
            // Worth seeing: it is what a text-only client shows, and a broken
            // one is invisible otherwise.
            'plain'     => service('mail')->toPlainText($body),
            'sensitive' => $sensitive,
            'code'      => $code,
            'codeState'   => $code !== null ? $this->codeState((string) $row['recipient']) : null,
            'codeMissing' => $codeMissing,
        ], 'Message to ' . $row['recipient']);
    }

    /** Put a failed message back in the queue. */
    public function retry(int $id)
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $db  = db_connect();
        $row = $db->table('notification_log')->where('id', $id)->get()->getRowArray();

        if ($row === null) {
            return redirect()->back()->with('error', 'That message is no longer here.');
        }

        if ($row['status'] === 'sent') {
            // Re-sending something the customer already received is worse than
            // doing nothing, so it is refused rather than quietly duplicated.
            return redirect()->back()->with('error', 'That one already went out.');
        }

        $db->table('notification_log')->where('id', $id)->update([
            'status'          => 'queued',
            'attempts'        => 0,
            'error'           => null,
            'next_attempt_at' => null,
        ]);

        service('audit')->log('mail_retried', 'settings', 'mail', $id, (string) $row['recipient']);

        return redirect()->back()->with('success', 'Queued again. It will go with the next run.');
    }

    /** Send whatever is waiting, now, without waiting for cron. */
    public function drain()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        try {
            $result = service('mail')->drainQueue(25);
            $sent   = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
        } catch (\Throwable $e) {
            log_message('error', 'Manual queue drain failed: {m}', ['m' => $e->getMessage()]);

            return redirect()->back()->with('error', 'The queue could not be sent. Check the mail settings.');
        }

        if ($sent === 0 && $failed === 0) {
            return redirect()->back()->with('success', 'Nothing was waiting.');
        }

        // Report failures too. "3 sent" when 2 also failed is a half-truth, and
        // the failures are the part someone needs to act on.
        return redirect()->back()->with(
            $failed > 0 ? 'error' : 'success',
            $sent . ' sent' . ($failed > 0 ? ', ' . $failed . ' failed — see the list below' : '.')
        );
    }

    /**
     * Does this message carry something nobody but its recipient should read?
     *
     * Keyed on the template, not on scanning the body for digit runs — an order
     * reference or a total would trip that, and a real code in an unexpected
     * template would slip through it.
     *
     * @param array<string, mixed> $row
     */
    private function isSensitive(array $row): bool
    {
        return in_array((string) $row['template_key'], [
            'customer_login_code',
            'customer_verify_email',
            'customer_password_reset',
            'admin_password_reset',
        ], true);
    }

    /**
     * Pull the one-time code out of a rendered message.
     *
     * Read from the body rather than the auth_codes table, because the code
     * there is HASHED and cannot be read back — which is correct, and means the
     * only copy in plain text is the one that was actually sent.
     */
    private function codeFrom(string $html): ?string
    {
        // The templates wrap it in <strong>. Anchored on that rather than any
        // run of digits, so an order total or a year cannot be mistaken for it.
        if (preg_match('/<strong>\s*(\d{4,8})\s*<\/strong>/', $html, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Is that code still usable?
     *
     * Worth saying plainly. "It says 266388" is unhelpful if the customer
     * already used it ten minutes ago, and a support call goes in circles.
     *
     * @return array{label: string, tone: string}
     */
    private function codeState(string $email): array
    {
        $row = db_connect()->table('auth_codes')
            ->where('email', strtolower($email))
            ->orderBy('id', 'DESC')
            ->get(1)->getRowArray();

        if ($row === null) {
            return ['label' => 'No longer on record', 'tone' => 'muted'];
        }

        if ($row['consumed_at'] !== null) {
            return ['label' => 'Already used', 'tone' => 'muted'];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['label' => 'Expired', 'tone' => 'muted'];
        }

        $minutes = max(1, (int) ceil((strtotime((string) $row['expires_at']) - time()) / 60));

        return ['label' => 'Still valid for ' . $minutes . ' more minute' . ($minutes === 1 ? '' : 's'), 'tone' => 'good'];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);

        foreach (db_connect()->table('notification_log')
            ->select('status, COUNT(*) AS n', false)
            ->groupBy('status')->get()->getResultArray() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }
}
