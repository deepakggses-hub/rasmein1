<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * The media library.
 *
 * @var array<int, array<string, mixed>> $items
 * @var list<string> $folders
 */
$pages = (int) ceil(max(1, $total) / $perPage);
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Media library',
    'subheading' => 'Everything uploaded, in one place. Search it, describe it, remove what is unused.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label class="min-w-56 flex-1">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input" value="<?= esc($term, 'attr') ?>"
                   placeholder="By file name, or by what the picture shows&hellip;">
        </label>

        <?php if ($folders !== []): ?>
            <label>
                <span class="rs-label">Folder</span>
                <select name="collection" class="rs-select">
                    <option value="">All</option>
                    <?php foreach ($folders as $folder): ?>
                        <option value="<?= esc($folder, 'attr') ?>"
                                <?= $collection === $folder ? 'selected' : '' ?>>
                            <?= esc($folder) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Search</button>

        <?php if ($term !== '' || $collection !== ''): ?>
            <a href="<?= site_url('admin/media-library') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
        <?php endif; ?>

        <p class="rs-help ml-auto">
            <span class="num"><?= (int) $total ?></span> picture<?= $total === 1 ? '' : 's' ?>
        </p>
    </form>

    <?php if ($items !== []): ?>
        <?php /* The bar only appears once something is ticked — an always-visible
                 "delete 0 selected" is a control that does nothing. */ ?>
        <div class="rs-bulkbar" data-bulk-bar hidden>
            <p class="text-sm">
                <span class="num" data-bulk-count>0</span> selected
            </p>

            <div class="ml-auto flex gap-2">
                <button type="button" class="rs-btn rs-btn--outline rs-btn--sm" data-bulk-none>
                    Clear
                </button>
                <button type="submit" form="rs-bulk-form" class="rs-btn rs-btn--sm bg-bad text-shell"
                        data-confirm="Delete the selected pictures? This cannot be undone.">
                    Delete selected
                </button>
            </div>
        </div>

        <form method="post" action="<?= site_url('admin/media/bulk-delete') ?>" id="rs-bulk-form">
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="mt-6 border border-shell-line bg-white p-10 text-center">
            <p class="font-display text-xl">Nothing here.</p>
            <p class="rs-help mt-2">
                <?= $term !== '' || $collection !== ''
                    ? 'Nothing matches that search.'
                    : 'Upload a picture on any screen and it appears here.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach ($items as $item): ?>
                <article class="relative flex flex-col border border-shell-line bg-white" data-media-tile>
                    <?php /* The checkbox belongs to the bulk form by `form=`, so
                             it can sit inside the tile without nesting forms —
                             which is invalid and silently drops the inner one. */ ?>
                    <label class="absolute top-2 left-2 z-10 flex h-7 w-7 cursor-pointer items-center
                                  justify-center rounded border border-shell-line bg-white/95">
                        <span class="sr-only">Select <?= esc($item['filename']) ?></span>
                        <input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>"
                               form="rs-bulk-form" class="accent-mulberry" data-bulk-check>
                    </label>

                    <?php /* The picture links to itself at full size — checking
                             what something IS should not need a download. */ ?>
                    <a href="<?= rs_url($item['path']) ?>" target="_blank" rel="noopener"
                       class="block aspect-square overflow-hidden bg-shell-deep">
                        <img src="<?= rs_image($item['path'], 'thumb') ?>" alt=""
                             loading="lazy" class="h-full w-full object-cover">
                    </a>

                    <div class="flex flex-1 flex-col gap-3 p-3">
                        <div>
                            <p class="truncate font-mono text-[0.6875rem] text-ink-muted"
                               title="<?= esc($item['filename'], 'attr') ?>">
                                <?= esc($item['filename']) ?>
                            </p>
                            <p class="rs-help">
                                <?= $item['width'] !== null
                                    ? (int) $item['width'] . ' × ' . (int) $item['height']
                                    : 'unknown size' ?>
                                <?php if ((int) $item['bytes'] > 0): ?>
                                    &middot; <?= number_format($item['bytes'] / 1024) ?> KB
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php /* Alt text saves on its own, because describing a
                                 picture is the one thing people do here and it
                                 should not need a second screen. */ ?>
                        <form method="post" action="<?= site_url('admin/media/' . (int) $item['id'] . '/alt') ?>"
                              class="mt-auto grid gap-2">
                            <?= csrf_field() ?>
                            <label>
                                <span class="sr-only">What this picture shows</span>
                                <input type="text" name="alt_text" class="rs-input text-xs"
                                       maxlength="191" placeholder="What does it show?"
                                       value="<?= esc($item['alt_text'] ?? '', 'attr') ?>">
                            </label>

                            <div class="flex gap-2">
                                <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm flex-1">Save</button>
                            </div>
                        </form>

                        <form method="post" action="<?= site_url('admin/media/' . (int) $item['id'] . '/delete') ?>"
                              data-confirm="Delete this picture? This cannot be undone.">
                            <?= csrf_field() ?>
                            <button type="submit" class="rs-kicker text-ink-muted hover:text-bad">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-8 flex flex-wrap items-center justify-center gap-2" aria-label="Pages">
                <?php for ($n = 1; $n <= $pages; $n++): ?>
                    <a href="<?= site_url('admin/media-library') ?>?page=<?= $n ?><?= $term !== '' ? '&q=' . urlencode($term) : '' ?><?= $collection !== '' ? '&collection=' . urlencode($collection) : '' ?>"
                       class="rs-chip <?= $n === $page ? 'is-on' : '' ?>"><?= $n ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
