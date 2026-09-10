<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Everything the header and footer show, on one screen.
 *
 * @var array<string, array<string, array<string, string>>> $fields
 * @var array<string, string> $values
 */
$val = static fn (string $k): string => (string) (old($k) ?? ($values[$k] ?? ''));

$lines = static function (string $raw): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $raw) ?: [])));
};
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Settings',
    'heading'    => 'Header & footer',
    'subheading' => 'Navigation, search wording, footer links and contact details.',
    'actions'    => '<a href="' . site_url() . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View the site</a>',
]) ?>

<form method="post" action="<?= site_url('admin/chrome') ?>" class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid max-w-5xl gap-6">
        <?php foreach ($fields as $section => $group): ?>
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain"><?= esc($section) ?></h2>

                <div class="mt-5 grid gap-4 <?= $section === 'Contact and social' ? 'sm:grid-cols-2' : '' ?>">
                    <?php foreach ($group as $key => $meta): ?>
                        <label>
                            <span class="rs-label"><?= esc($meta['label']) ?></span>

                            <?php if ($meta['type'] === 'list'): ?>
                                <?php $items = $lines($val($key)); ?>
                                <?php /* Monospaced, because these lines have a
                                         shape — "Label | /path" — and a
                                         proportional font hides a missing pipe. */ ?>
                                <textarea name="<?= esc($key, 'attr') ?>" rows="<?= max(4, min(14, count($items) + 2)) ?>"
                                          class="rs-textarea font-mono text-xs"
                                          maxlength="4000"><?= esc(implode("\n", $items)) ?></textarea>
                                <span class="rs-help">
                                    <span class="num"><?= count($items) ?></span> line(s).
                                    <?= esc($meta['help'] ?? '') ?>
                                </span>

                            <?php elseif ($meta['type'] === 'long'): ?>
                                <textarea name="<?= esc($key, 'attr') ?>" rows="3" class="rs-textarea"
                                          maxlength="1000"><?= esc($val($key)) ?></textarea>
                                <?php if (! empty($meta['help'])): ?>
                                    <span class="rs-help"><?= esc($meta['help']) ?></span>
                                <?php endif; ?>

                            <?php else: ?>
                                <input type="text" name="<?= esc($key, 'attr') ?>" class="rs-input"
                                       maxlength="255" value="<?= esc($val($key), 'attr') ?>">
                                <?php if (! empty($meta['help'])): ?>
                                    <span class="rs-help"><?= esc($meta['help']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rs-btn rs-btn--primary">Save</button>
            <span class="rs-help">
                The logo, palette, contact details and social links all live under
                <a href="<?= site_url('admin/brand') ?>" class="rs-link text-mulberry">Shop identity</a>,
                so they are written once and used everywhere.
            </span>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
