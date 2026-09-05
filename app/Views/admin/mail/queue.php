<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * The mail queue.
 *
 * Message BODIES are not shown. A queued message can hold an address, an order
 * total or a one-time sign-in code, and none of that belongs in a table anyone
 * with panel access can scroll past.
 *
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, int> $counts
 */
$badge = static fn (string $s): string => match ($s) {
    'sent'    => 'rs-badge--soft',
    'failed'  => 'rs-badge--out',
    'sending' => 'rs-badge--brass',
    default   => '',
};
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Mail',
    'heading'    => 'Mail queue',
    'subheading' => 'Everything the shop has tried to send, and what happened to it.',
    'actions'    => '<a href="' . site_url('admin/mail') . '" class="rs-btn rs-btn--outline rs-btn--sm">Mail settings</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <!-- ============================== state ============================== -->
    <section class="grid gap-3 sm:grid-cols-4">
        <?php foreach ($statuses as $s): ?>
            <a href="<?= site_url('admin/mail/queue?status=' . $s) ?>"
               class="rs-stat block hover:border-mulberry <?= $status === $s ? 'border-mulberry' : '' ?> <?= $s === 'failed' && $counts[$s] > 0 ? 'rs-stat--alert' : '' ?>">
                <span class="rs-stat__label"><?= ucfirst($s) ?></span>
                <span class="rs-stat__value num <?= $counts[$s] === 0 ? 'text-ink-muted' : '' ?>">
                    <?= (int) $counts[$s] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </section>

    <!-- ============================= controls =========================== -->
    <div class="mt-6 flex flex-wrap items-end justify-between gap-3">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
            <?php endif; ?>
            <label>
                <span class="rs-label">Find</span>
                <input type="search" name="q" class="rs-input" placeholder="Address or subject"
                       value="<?= esc($search, 'attr') ?>">
            </label>
            <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Search</button>
            <?php if ($status !== '' || $search !== ''): ?>
                <a href="<?= site_url('admin/mail/queue') ?>" class="rs-link text-xs text-ink-muted">Clear</a>
            <?php endif; ?>
        </form>

        <form method="post" action="<?= site_url('admin/mail/queue/drain') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Send what is waiting</button>
        </form>
    </div>

    <!-- =============================== list ============================= -->
    <div class="mt-5 border border-shell-line bg-white">
        <?php if ($rows === []): ?>
            <p class="px-5 py-12 text-center text-sm text-ink-muted">
                <?= $status !== '' || $search !== '' ? 'Nothing matches that.' : 'No mail has been queued yet.' ?>
            </p>
        <?php else: ?>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($rows as $row): ?>
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3">
                        <span class="rs-badge <?= $badge((string) $row['status']) ?> shrink-0">
                            <?= esc($row['status']) ?>
                        </span>

                        <div class="min-w-56 flex-1">
                            <p class="text-sm font-medium">
                                <a href="<?= site_url('admin/mail/queue/' . $row['id']) ?>" class="rs-link hover:text-mulberry">
                                    <?= esc($row['subject'] ?: '(no subject)') ?>
                                </a>
                            </p>
                            <p class="rs-help">
                                <?= esc($row['recipient']) ?>
                                <?php if (! empty($row['template_key'])): ?>
                                    <span class="mx-1 text-brass" aria-hidden="true">&middot;</span>
                                    <span class="font-mono"><?= esc($row['template_key']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if (! empty($row['error'])): ?>
                                <?php /* Truncated: an SMTP failure can run to
                                         hundreds of characters and the useful
                                         part is always at the front. */ ?>
                                <p class="rs-help text-bad"><?= esc(mb_substr((string) $row['error'], 0, 160)) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (! empty($row['otp'])): ?>
                            <?php /* Right here in the row. This queue is the
                                     only inbox until SMTP is set up, and
                                     opening a message to read six digits is a
                                     step for nothing. */ ?>
                            <span class="shrink-0 rounded bg-brass-soft px-2.5 py-1 font-mono text-sm tracking-[0.2em] text-mulberry">
                                <?= esc($row['otp']) ?>
                            </span>
                        <?php endif; ?>

                        <div class="shrink-0 text-right">
                            <p class="num font-mono text-[0.625rem] text-ink-muted">
                                <?= esc(date('j M, H:i', strtotime((string) ($row['sent_at'] ?: $row['created_at'])))) ?>
                            </p>
                            <?php if ((int) $row['attempts'] > 0): ?>
                                <p class="num font-mono text-[0.625rem] text-ink-muted">
                                    <?= (int) $row['attempts'] ?> attempt<?= (int) $row['attempts'] === 1 ? '' : 's' ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($row['status'] !== 'sent'): ?>
                            <form method="post" action="<?= site_url('admin/mail/queue/' . $row['id'] . '/retry') ?>"
                                  class="shrink-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Try again</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="rs-help border-t border-shell-line px-5 py-3">
                Showing the most recent <?= count($rows) ?>. Open one to read what was sent,
                including any one-time code — which is recorded in the audit log.
            </p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
