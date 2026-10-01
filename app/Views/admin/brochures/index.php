<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Brochures, and which page offers which.
 *
 * @var array<int, array<string, mixed>> $brochures
 * @var array<int, int>                  $leadCounts
 * @var array<string, int>               $assignments  "type:id" => brochure id
 * @var array<string, array>             $targets
 * @var float|int                        $maxMb
 */
$default = null;

foreach ($brochures as $row) {
    if ((int) $row['is_default'] === 1) {
        $default = $row;

        break;
    }
}

$groups = [
    'category'   => 'Categories',
    'collection' => 'Occasions and collections',
    'page'       => 'Content pages',
];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Brochures',
    'subheading' => 'A downloadable PDF per page, with one default for everywhere else. Every download leaves a lead.',
    'actions'    => '<a href="' . site_url('admin/brochures/leads')
        . '" class="rs-btn rs-btn--outline rs-btn--sm">Download leads</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <?php if ($default === null): ?>
        <p class="mb-6 border border-bad/30 bg-bad/5 px-4 py-3 text-sm">
            <strong>No default brochure yet.</strong>
            Until one is set, a page with nothing of its own shows no download button
            at all. The first brochure uploaded becomes the default automatically.
        </p>
    <?php endif; ?>

    <!-- -------------------------------------------------------- upload -->
    <div class="border border-shell-line bg-white p-5">
        <h2 class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">Add a brochure</h2>

        <form method="post" action="<?= site_url('admin/brochures') ?>" enctype="multipart/form-data"
              class="mt-4 grid gap-4 md:grid-cols-2">
            <?= csrf_field() ?>

            <label class="block">
                <span class="rs-label">Title</span>
                <input type="text" name="title" class="rs-input" required maxlength="191"
                       value="<?= esc(old('title'), 'attr') ?>"
                       placeholder="Diwali Catalogue 2026">
            </label>

            <label class="block">
                <span class="rs-label">PDF <span class="text-ink-muted">(max <?= (int) $maxMb ?> MB)</span></span>
                <input type="file" name="brochure" class="rs-input" accept="application/pdf,.pdf" required>
                <?php if (! empty($phpCaps)): ?>
                    <?php /* Said here rather than only on failure: PHP throws the file
                             away before the application sees it, so the only other
                             place this can surface is an error after a long upload. */ ?>
                    <span class="mt-1 block text-xs text-bad">
                        This server caps uploads at <?= (int) $maxMb ?> MB
                        (<code>upload_max_filesize <?= esc($iniUpload) ?></code>,
                        <code>post_max_size <?= esc($iniPost) ?></code>).
                        Raise both in <code>php.ini</code> to allow the full
                        <?= (int) (\App\Services\BrochureService::MAX_BYTES / 1048576) ?> MB.
                    </span>
                <?php endif; ?>
            </label>

            <label class="block md:col-span-2">
                <span class="rs-label">Description <span class="text-ink-muted">(optional)</span></span>
                <textarea name="description" rows="2" class="rs-input"
                          placeholder="Shown under the download button."><?= esc(old('description')) ?></textarea>
            </label>

            <div class="flex items-center justify-between gap-4 md:col-span-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Available for download</span>
                </label>
                <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Upload brochure</button>
            </div>
        </form>
    </div>

    <!-- ---------------------------------------------------------- list -->
    <div class="mt-6 overflow-x-auto border border-shell-line bg-white">
        <?php if ($brochures === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-muted">Nothing uploaded yet.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Brochure</th>
                        <th class="num px-4 py-2.5 text-right">Size</th>
                        <th class="num px-4 py-2.5 text-right">Downloads</th>
                        <th class="num px-4 py-2.5 text-right">Leads</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($brochures as $row): ?>
                        <?php $id = (int) $row['id']; ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium"><?= esc($row['title']) ?></span>
                                    <?php if ((int) $row['is_default'] === 1): ?>
                                        <span class="rs-badge rs-badge--brass">Default</span>
                                    <?php endif; ?>
                                    <?php if ((int) $row['is_active'] !== 1): ?>
                                        <span class="rs-badge">Off</span>
                                    <?php endif; ?>
                                </div>
                                <p class="font-mono text-xs text-ink-muted"><?= esc($row['filename']) ?></p>
                                <?php if (! empty($row['description'])): ?>
                                    <p class="mt-1 text-xs text-ink-muted"><?= esc($row['description']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="num px-4 py-2.5 text-right text-xs text-ink-muted">
                                <?= number_format(((int) $row['bytes']) / 1048576, 1) ?> MB
                            </td>
                            <td class="num px-4 py-2.5 text-right"><?= (int) $row['download_count'] ?></td>
                            <td class="num px-4 py-2.5 text-right">
                                <a class="rs-link" href="<?= site_url('admin/brochures/leads?brochure=' . $id) ?>">
                                    <?= (int) ($leadCounts[$id] ?? 0) ?>
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <?php if ((int) $row['is_default'] !== 1): ?>
                                    <form method="post" action="<?= site_url('admin/brochures/' . $id . '/default') ?>"
                                          class="inline">
                                        <?= csrf_field() ?>
                                        <button class="rs-link text-xs">Make default</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/brochures/' . $id . '/delete') ?>"
                                          class="ml-3 inline"
                                          data-confirm="Remove this brochure?"
                                          data-confirm-detail="The file goes. The leads it produced are kept."
                                          data-confirm-action="Remove">
                                        <?= csrf_field() ?>
                                        <button class="rs-link text-xs text-bad">Remove</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-ink-muted">Shown wherever nothing else is set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- --------------------------------------------------- assignment -->
    <?php if ($brochures !== []): ?>
        <div class="mt-6 border border-shell-line bg-white p-5">
            <h2 class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">Which page offers which</h2>
            <p class="mt-1 text-xs text-ink-muted">
                Anything left on &ldquo;Default&rdquo; shows
                <?= $default !== null ? esc($default['title']) : 'nothing, until a default is set' ?>.
            </p>

            <form method="post" action="<?= site_url('admin/brochures/assign') ?>" class="mt-4">
                <?= csrf_field() ?>

                <?php foreach ($groups as $type => $label): ?>
                    <?php $rows = $targets[$type] ?? []; ?>
                    <?php if ($rows === []) { continue; } ?>

                    <details class="border-t border-shell-line py-3" <?= $type === 'category' ? 'open' : '' ?>>
                        <summary class="flex cursor-pointer items-center gap-2 font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">
                            <span class="flex-1"><?= esc($label) ?></span>
                            <span class="num"><?= count($rows) ?></span>
                        </summary>

                        <div class="mt-3 grid gap-2">
                            <?php foreach ($rows as $target): ?>
                                <?php $key = $type . ':' . $target['id']; ?>
                                <label class="flex items-center gap-3 text-sm">
                                    <span class="min-w-0 flex-1 truncate"><?= esc($target['label']) ?></span>
                                    <select name="target[<?= esc($key, 'attr') ?>]" class="rs-input w-56">
                                        <option value="">Default</option>
                                        <?php foreach ($brochures as $b): ?>
                                            <option value="<?= (int) $b['id'] ?>"
                                                <?= ($assignments[$key] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                                                <?= esc($b['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

                <div class="mt-5 text-right">
                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Save assignments</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
