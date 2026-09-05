<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Alt text for every image, grouped by where it lives.
 *
 * Missing ones are counted at the top and filterable, because the useful
 * question is "what still needs doing", not "list everything".
 */
$pct = $counts['total'] > 0
    ? (int) round((($counts['total'] - $counts['missing']) / $counts['total']) * 100)
    : 100;
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Image alt text',
    'subheading' => 'What a screen reader announces, and what shows when a picture fails to load.',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <!-- ============================== state ============================== -->
    <section class="grid gap-3 sm:grid-cols-3">
        <div class="rs-stat">
            <span class="rs-stat__label">Images with alt text</span>
            <span class="rs-stat__value num"><?= $pct ?>%</span>
            <span class="rs-stat__note num">
                <?= $counts['total'] - $counts['missing'] ?> of <?= $counts['total'] ?>
            </span>
        </div>
        <a href="<?= site_url('admin/media?missing=1') ?>"
           class="rs-stat block hover:border-mulberry <?= $counts['missing'] > 0 ? 'rs-stat--alert' : '' ?>">
            <span class="rs-stat__label">Still missing</span>
            <span class="rs-stat__value num <?= $counts['missing'] === 0 ? 'text-ink-muted' : '' ?>">
                <?= (int) $counts['missing'] ?>
            </span>
            <span class="rs-stat__note">Show only these</span>
        </a>
        <div class="rs-stat">
            <span class="rs-stat__label">Why it matters</span>
            <p class="mt-2 text-xs leading-relaxed text-ink-soft">
                Describe what the picture shows, not that it is a picture. Leave it blank only
                when the image adds nothing the words beside it do not already say.
            </p>
        </div>
    </section>

    <!-- ============================= filters ============================= -->
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <a href="<?= site_url('admin/media') ?>"
           class="rs-chip <?= $only === '' && ! $missing ? 'border-mulberry text-mulberry' : '' ?>">
            Everything
        </a>
        <?php foreach ($sources as $key => $label): ?>
            <a href="<?= site_url('admin/media?source=' . urlencode($key)) ?>"
               class="rs-chip <?= $only === $key ? 'border-mulberry text-mulberry' : '' ?>">
                <?= esc($label) ?>
            </a>
        <?php endforeach; ?>
        <?php if ($missing): ?>
            <span class="rs-chip border-brass text-brass">Missing only</span>
        <?php endif; ?>
    </div>

    <!-- ============================== list =============================== -->
    <?php if ($groups === []): ?>
        <p class="mt-8 border border-shell-line bg-white px-5 py-10 text-center text-sm text-ink-muted">
            <?= $missing ? 'Every image has alt text. Nothing to do.' : 'No images uploaded yet.' ?>
        </p>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/media') ?>" class="mt-6 space-y-6">
            <?= csrf_field() ?>

            <?php foreach ($groups as $key => $group): ?>
                <section class="border border-shell-line bg-white">
                    <div class="flex items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
                        <h2 class="rs-eyebrow rs-eyebrow--plain"><?= esc($group['label']) ?></h2>
                        <span class="num font-mono text-[0.625rem] text-ink-muted">
                            <?= count($group['rows']) ?>
                        </span>
                    </div>

                    <ul class="divide-y divide-shell-line">
                        <?php foreach ($group['rows'] as $row): ?>
                            <?php $empty = trim((string) $row['alt_text']) === ''; ?>
                            <li class="flex flex-wrap items-center gap-4 px-5 py-3">
                                <span class="block h-14 w-14 shrink-0 overflow-hidden bg-shell-deep">
                                    <img src="<?= rs_image($row['image'], 'products') ?>" alt=""
                                         loading="lazy" decoding="async"
                                         class="h-full w-full object-cover">
                                </span>

                                <span class="min-w-40 flex-1">
                                    <span class="block text-sm font-medium"><?= esc($row['title'] ?? 'Untitled') ?></span>
                                    <a href="<?= site_url($row['editUrl']) ?>"
                                       class="rs-link font-mono text-[0.625rem] text-ink-muted">
                                        Open where it lives
                                    </a>
                                </span>

                                <label class="min-w-64 flex-[2]">
                                    <span class="sr-only">Alt text for <?= esc($row['title'] ?? 'this image') ?></span>
                                    <input type="text"
                                           name="alt[<?= esc($row['source'] . ':' . $row['id'], 'attr') ?>]"
                                           class="rs-input <?= $empty ? 'border-brass' : '' ?>"
                                           maxlength="191"
                                           placeholder="A wedding hamper wrapped in ivory linen with a maroon ribbon"
                                           value="<?= esc((string) $row['alt_text'], 'attr') ?>">
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rs-btn rs-btn--primary">Save alt text</button>
                <span class="rs-help">Only the rows you changed are written.</span>
            </div>
        </form>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
