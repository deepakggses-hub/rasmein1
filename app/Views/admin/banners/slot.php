<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * One slot, on its own page.
 *
 * @var string $slot     The stored position, e.g. home_hero
 * @var array  $meta     Its label, note, ideal size and whether bulk applies
 * @var array  $banners
 */
$now = time();
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Banners',
    'heading'    => $meta['label'],
    'subheading' => $meta['note'],
    'actions'    => '<a href="' . site_url('admin/banners/' . $meta['key'] . '/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">Add one</a>'
        . '<a href="' . site_url('admin/banners') . '" class="rs-btn rs-btn--outline rs-btn--sm">All slots</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <!-- ============================ bulk upload =========================== -->
    <?php if ($meta['multi']): ?>
        <form method="post" action="<?= site_url('admin/banners/bulk') ?>" enctype="multipart/form-data"
              class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="position" value="<?= esc($slot, 'attr') ?>">

            <label class="min-w-56 flex-1">
                <span class="rs-label">Add several at once</span>
                <input type="file" name="images[]" class="rs-input" multiple
                       accept="image/jpeg,image/png,image/webp">
                <span class="rs-help">
                    <?= esc($meta['ratio']) ?>. Each becomes its own banner, so they can be
                    reordered or removed individually.
                </span>
            </label>

            <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Upload</button>
        </form>
    <?php endif; ?>

    <!-- ================================ list ============================= -->
    <div class="<?= $meta['multi'] ? 'mt-6' : '' ?> border border-shell-line bg-white">
        <?php if ($banners === []): ?>
            <p class="px-5 py-10 text-center text-sm text-ink-muted">
                Nothing here yet. This part of the site stays hidden until there is.
                <a href="<?= site_url('admin/banners/' . $meta['key'] . '/new') ?>" class="rs-link text-mulberry">
                    Add the first
                </a>.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($banners as $banner): ?>
                    <?php
                    $bare    = \App\Models\BannerModel::isBare($banner);
                    $started = $banner['starts_at'] === null || strtotime((string) $banner['starts_at']) <= $now;
                    $ended   = $banner['ends_at'] !== null && strtotime((string) $banner['ends_at']) < $now;
                    ?>
                    <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <span class="block h-16 w-28 shrink-0 overflow-hidden bg-shell-deep">
                            <img src="<?= rs_image($banner['image'] ?? null, 'banners') ?>" alt=""
                                 loading="lazy" decoding="async" class="h-full w-full object-cover">
                        </span>

                        <div class="min-w-48 flex-1">
                            <p class="text-sm font-medium"><?= esc($banner['title'] ?: 'Picture only') ?></p>
                            <p class="rs-help">
                                <?php if ($bare): ?>
                                    Shown whole with no text over it<?= $banner['link_url']
                                        ? ', linked to /' . esc(ltrim((string) $banner['link_url'], '/'))
                                        : ' — no link set' ?>.
                                <?php else: ?>
                                    Heading and button<?= ! empty($banner['cta_label_2']) ? 's' : '' ?> over the picture.
                                <?php endif; ?>
                            </p>
                            <?php if (trim((string) ($banner['alt_text'] ?? '')) === ''): ?>
                                <p class="rs-help text-brass">
                                    No description —
                                    <a href="<?= site_url('admin/media?source=banner') ?>" class="rs-link">add one</a>.
                                </p>
                            <?php endif; ?>
                        </div>

                        <span class="num font-mono text-[0.625rem] text-ink-muted">#<?= (int) $banner['sort_order'] ?></span>

                        <span class="rs-badge <?= ! $banner['is_active'] || $ended ? 'rs-badge--out' : ($started ? 'rs-badge--soft' : 'rs-badge--brass') ?>">
                            <?= ! $banner['is_active'] ? 'Off' : ($ended ? 'Finished' : ($started ? 'Live' : 'Scheduled')) ?>
                        </span>

                        <a href="<?= site_url('admin/banners/' . $banner['id'] . '/edit') ?>"
                           class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- ============================ other slots =========================== -->
    <div class="mt-6 flex flex-wrap gap-2">
        <?php foreach ($slots as $position => $info): ?>
            <a href="<?= site_url('admin/banners/' . $info['key']) ?>"
               class="rs-chip <?= $position === $slot ? 'border-mulberry text-mulberry' : '' ?>">
                <?= esc($info['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
