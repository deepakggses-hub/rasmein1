<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * One queued or sent message, in full.
 *
 * The body renders inside a SANDBOXED iframe. It is HTML this application
 * generated and sanitised, but an email template is editable from the panel, so
 * a badly-edited one must not be able to run script against the admin session.
 * `sandbox` with no allow-* tokens means no scripts, no forms, no navigation.
 *
 * A one-time code is shown in full, with whether it still works. Until SMTP is
 * configured this queue is the only inbox there is, and in support the code is
 * the entire question. Looking is audited rather than prevented.
 *
 * @var array<string, mixed> $row
 * @var string $body
 * @var bool   $sensitive
 */
$badge = match ((string) $row['status']) {
    'sent'    => 'rs-badge--soft',
    'failed'  => 'rs-badge--out',
    'sending' => 'rs-badge--brass',
    default   => '',
};
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Mail queue',
    'heading'    => $row['subject'] ?: '(no subject)',
    'subheading' => 'To ' . $row['recipient'],
    'actions'    => '<a href="' . site_url('admin/mail/queue') . '" class="rs-btn rs-btn--outline rs-btn--sm">Back to the queue</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">

        <!-- =============================== body ========================== -->
        <section class="border border-shell-line bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
                <h2 class="rs-eyebrow rs-eyebrow--plain">What was sent</h2>
            </div>



            <?php /* Two views of the same message: the HTML a normal client
                     renders, and the plain-text alternative that rides along
                     with it. A broken plain-text version is invisible until
                     someone reads mail in a text-only client. */ ?>
            <div class="flex items-center gap-1 border-b border-shell-line px-5 py-2" role="tablist">
                <button type="button" class="rs-mailtab is-current" data-mail-tab="html" role="tab"
                        aria-selected="true">As received</button>
                <button type="button" class="rs-mailtab" data-mail-tab="plain" role="tab"
                        aria-selected="false">Plain text</button>
            </div>

            <div class="p-2" data-mail-pane="html">
                <?php /* srcdoc + sandbox: rendered exactly as the customer saw
                         it, but it cannot run script, submit a form, or
                         navigate the panel away. Resized to its own content by
                         the script, so a long email is not trapped in a short
                         box. */ ?>
                <?php
                /*
                 * `allow-same-origin` so the height can be measured.
                 *
                 * A bare `sandbox` makes the document opaque even to us, so the
                 * frame stays at whatever fixed height it was given — cropping a
                 * long email or leaving white space under a short one. Scripts
                 * stay OFF (no allow-scripts), which is the token that actually
                 * matters: an email template edited badly still cannot run
                 * anything against the panel.
                 */
                ?>
                <iframe title="The message, as received"
                        sandbox="allow-same-origin"
                        class="w-full border-0 bg-white"
                        style="height: 40rem"
                        data-mail-frame
                        srcdoc="<?= esc($body, 'attr') ?>"></iframe>
            </div>

            <div class="p-5" data-mail-pane="plain" hidden>
                <pre class="max-h-[40rem] overflow-auto whitespace-pre-wrap break-words bg-shell-deep p-4 font-mono text-xs leading-relaxed"><?= esc($plain) ?></pre>
            </div>
        </section>

        <!-- ============================== facts ========================== -->
        <aside class="space-y-5">
            <?php if (! empty($codeMissing)): ?>
                <?php /* Loud on purpose. A sign-in email that went out with no
                         code in it is a broken email, and the shop needs to
                         know rather than wonder where the panel went. */ ?>
                <section class="border border-bad bg-bad/5 p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain text-bad">No code in this message</h2>
                    <p class="rs-help mt-3">
                        This one was queued before the template placeholders were fixed, so it went
                        out with an empty code — the recipient received a blank. The code cannot be
                        recovered: it is stored hashed, on purpose.
                    </p>
                    <p class="rs-help mt-3">
                        Newer messages show the code here. Trigger a fresh sign-in to see one.
                    </p>
                </section>
            <?php endif; ?>

            <?php if ($code !== null): ?>
                <section class="border border-brass bg-brass-soft/20 p-5 text-center">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">One-time code</h2>

                    <p class="mt-3 font-mono text-3xl tracking-[0.35em] text-mulberry">
                        <?= esc($code) ?>
                    </p>

                    <p class="rs-help mt-2 <?= ($codeState['tone'] ?? '') === 'good' ? 'text-good' : '' ?>">
                        <?= esc($codeState['label'] ?? '') ?>
                    </p>

                    <p class="rs-help mt-3 border-t border-brass/40 pt-3 text-left">
                        Reading this is recorded in the audit log, against your name and this
                        customer's address.
                    </p>
                </section>
            <?php endif; ?>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Delivery</h2>

                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-muted">Status</dt>
                        <dd><span class="rs-badge <?= $badge ?>"><?= esc($row['status']) ?></span></dd>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-ink-muted">To</dt>
                        <dd class="break-all text-right"><?= esc($row['recipient']) ?></dd>
                    </div>

                    <?php if (! empty($row['template_key'])): ?>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-ink-muted">Template</dt>
                            <dd class="text-right font-mono text-xs"><?= esc($row['template_key']) ?></dd>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-muted">Queued</dt>
                        <dd class="num text-xs"><?= esc(date('j M Y, H:i', strtotime((string) $row['created_at']))) ?></dd>
                    </div>

                    <?php if (! empty($row['sent_at'])): ?>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">Sent</dt>
                            <dd class="num text-xs"><?= esc(date('j M Y, H:i', strtotime((string) $row['sent_at']))) ?></dd>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-muted">Attempts</dt>
                        <dd class="num"><?= (int) $row['attempts'] ?></dd>
                    </div>

                    <?php if (! empty($row['next_attempt_at'])): ?>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">Next try</dt>
                            <dd class="num text-xs"><?= esc(date('j M, H:i', strtotime((string) $row['next_attempt_at']))) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if (! empty($row['related_type']) && ! empty($row['related_id'])): ?>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">About</dt>
                            <dd class="text-xs"><?= esc($row['related_type']) ?> #<?= (int) $row['related_id'] ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if (! empty($row['error'])): ?>
                    <div class="mt-4 border-t border-shell-line pt-4">
                        <span class="rs-label">What went wrong</span>
                        <p class="mt-1 break-words text-xs leading-relaxed text-bad"><?= esc($row['error']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($row['status'] !== 'sent'): ?>
                    <form method="post" action="<?= site_url('admin/mail/queue/' . $row['id'] . '/retry') ?>" class="mt-5">
                        <?= csrf_field() ?>
                        <button type="submit" class="rs-btn rs-btn--primary w-full">Try sending again</button>
                    </form>
                <?php endif; ?>
            </section>

            <p class="rs-help">Opening a message is recorded in the audit log.</p>
        </aside>
    </div>
</div>

<?= $this->endSection() ?>
