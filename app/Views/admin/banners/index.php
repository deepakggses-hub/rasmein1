<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Which slot? Each one opens its own page.
 *
 * A chooser rather than every slot stacked together: someone managing the
 * gallery should not scroll past six other slots to reach it, and the page they
 * land on should have an address they can bookmark.
 */
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Banners',
    'subheading' => 'Pick the part of the site you want to change.',
    'actions'    => '<a href="' . site_url('/') . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View the site</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($slots as $position => $meta): ?>
            <?php $count = (int) ($counts[$position] ?? 0); ?>
            <li>
                <a href="<?= site_url('admin/banners/' . $meta['key']) ?>"
                   class="rs-card flex h-full flex-col bg-white p-5 hover:border-mulberry">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold"><?= esc($meta['label']) ?></h2>
                        <span class="num shrink-0 font-display text-2xl <?= $count === 0 ? 'text-ink-muted' : 'text-mulberry' ?>">
                            <?= $count ?>
                        </span>
                    </div>

                    <p class="rs-help mt-2 flex-1"><?= esc($meta['note']) ?></p>

                    <p class="rs-help num mt-3 border-t border-shell-line pt-3 font-mono text-[0.625rem]">
                        <?= esc($meta['ratio']) ?>
                        <?php if ($meta['multi']): ?>
                            <span class="ml-2 text-brass">Bulk upload</span>
                        <?php endif; ?>
                    </p>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?= $this->endSection() ?>
