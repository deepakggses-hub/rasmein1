<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$tone = [
    'urgent'  => 'border-bad',
    'warning' => 'border-warn',
    'success' => 'border-pista-deep',
    'info'    => 'border-shell-line',
];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Overview',
    'heading'    => 'Notifications',
    'subheading' => $unread === 0
        ? 'Everything here has been read.'
        : $unread . ' unread.',
    'actions'    => $unread > 0
        ? '<form method="post" action="' . site_url('admin/notifications/read-all') . '">'
          . csrf_field()
          . '<button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Mark all read</button></form>'
        : '',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="flex gap-2">
        <?php foreach (['all' => 'Everything', 'unread' => 'Unread only'] as $key => $label): ?>
            <a href="<?= site_url('admin/notifications') . ($key === 'unread' ? '?show=unread' : '') ?>"
               class="rs-btn rs-btn--sm <?= $show === $key ? 'rs-btn--primary' : 'rs-btn--outline' ?>">
                <?= esc($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-5 border border-shell-line bg-white">
        <?php if ($notifications === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-muted">
                <?= $show === 'unread' ? 'Nothing unread.' : 'No notifications yet.' ?>
            </p>
        <?php else: ?>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($notifications as $note): ?>
                    <li class="flex items-start gap-4 border-l-2 <?= $tone[$note['severity']] ?? 'border-shell-line' ?>
                               px-4 py-3 <?= $note['is_read'] ? '' : 'bg-shell' ?>">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm <?= $note['is_read'] ? '' : 'font-semibold' ?>">
                                <?php if (! $note['is_read']): ?>
                                    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-mulberry" aria-label="Unread"></span>
                                <?php endif; ?>
                                <?= esc($note['title']) ?>
                            </p>
                            <?php if (! empty($note['body'])): ?>
                                <p class="mt-0.5 text-xs text-ink-muted"><?= esc($note['body']) ?></p>
                            <?php endif; ?>
                            <p class="num mt-1 font-mono text-[0.5625rem] tracking-[0.12em] text-ink-muted uppercase">
                                <?= esc(str_replace('_', ' ', $note['event'])) ?> ·
                                <?= esc(date('j M y, H:i', strtotime((string) $note['created_at']))) ?>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <?php if (! empty($note['link_url'])): ?>
                                <form method="post" action="<?= site_url('admin/notifications/' . $note['id'] . '/read') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="link" value="<?= esc($note['link_url'], 'attr') ?>">
                                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Open</button>
                                </form>
                            <?php elseif (! $note['is_read']): ?>
                                <form method="post" action="<?= site_url('admin/notifications/' . $note['id'] . '/read') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="rs-link text-xs text-ink-muted">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= view('admin/partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
