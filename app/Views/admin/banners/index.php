<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$positions = [
    'home_hero' => 'Homepage hero', 'home_strip' => 'Homepage strip',
    'category_top' => 'Category top', 'gift_builder' => 'Gift builder',
];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Banners',
    'subheading' => 'Scheduled promotional slots. A banner outside its window simply does not show.',
    'actions'    => '<a href="' . site_url('admin/banners/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New banner</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($banners === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No banners yet.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Banner</th>
                        <th class="px-4 py-2.5">Slot</th>
                        <th class="px-4 py-2.5">Window</th>
                        <th class="px-4 py-2.5">Showing</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($banners as $banner): ?>
                        <?php
                        $now = time();
                        $started = $banner['starts_at'] === null || strtotime((string) $banner['starts_at']) <= $now;
                        $ended   = $banner['ends_at'] !== null && strtotime((string) $banner['ends_at']) < $now;
                        $live    = $banner['is_active'] && $started && ! $ended;
                        ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="block h-9 w-16 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= esc(rs_image($banner['image'], 'banners'), 'attr') ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <span>
                                        <a href="<?= site_url('admin/banners/' . $banner['id'] . '/edit') ?>" class="rs-link font-medium">
                                            <?= esc($banner['title'] ?: $banner['eyebrow'] ?: 'Untitled') ?>
                                        </a>
                                        <?php if (! empty($banner['subtitle'])): ?>
                                            <span class="block text-xs text-ink-muted"><?= esc(rs_excerpt($banner['subtitle'], 44)) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-ink-muted"><?= esc($positions[$banner['position']] ?? $banner['position']) ?></td>
                            <td class="num px-4 py-2.5 text-xs text-ink-muted">
                                <?= $banner['starts_at'] !== null ? esc(date('j M y', strtotime((string) $banner['starts_at']))) : 'any time' ?>
                                &ndash;
                                <?= $banner['ends_at'] !== null ? esc(date('j M y', strtotime((string) $banner['ends_at']))) : 'open' ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <?php if ($live): ?>
                                    <span class="rs-badge rs-badge--enquire">Live now</span>
                                <?php elseif (! $banner['is_active']): ?>
                                    <span class="rs-badge rs-badge--out">Off</span>
                                <?php elseif ($ended): ?>
                                    <span class="rs-badge rs-badge--out">Finished</span>
                                <?php else: ?>
                                    <span class="rs-badge rs-badge--soft">Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="<?= site_url('admin/banners/' . $banner['id'] . '/edit') ?>"
                                   class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
