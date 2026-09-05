<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * What to export, and how.
 *
 * Counts are shown against every option so nobody exports an empty selection
 * and has to open the file to discover it.
 *
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $occasions
 * @var int $total
 */
$csv = static fn (string $q = ''): string => site_url('admin/catalogue/export.csv' . ($q !== '' ? '?' . $q : ''));
$sql = static fn (string $q = ''): string => site_url('admin/catalogue/export.sql' . ($q !== '' ? '?' . $q : ''));
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Export',
    'subheading' => 'One row per variant, so the whole grid can be filtered in a spreadsheet.',
    'actions'    => '<a href="' . site_url('admin/products') . '" class="rs-btn rs-btn--outline rs-btn--sm">Back to products</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-start">

        <!-- =============================== everything ==================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">The whole catalogue</h2>
            <p class="rs-help mt-2">
                Every product and every variant &mdash; <span class="num"><?= (int) $total ?></span> products.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="<?= $csv() ?>" class="rs-btn rs-btn--primary rs-btn--sm">Spreadsheet (CSV)</a>
                <a href="<?= $sql() ?>" class="rs-btn rs-btn--outline rs-btn--sm">SQL dump</a>
            </div>

            <p class="rs-help mt-4 border-t border-shell-line pt-4">
                The SQL dump replaces the catalogue when replayed. A filtered one below only
                <em>adds</em> its own rows &mdash; it will not delete anything it was not given.
            </p>
        </section>

        <!-- ============================== by category ==================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">By category</h2>

            <?php if ($categories === []): ?>
                <p class="rs-help mt-3">No categories yet.</p>
            <?php else: ?>
                <ul class="mt-4 divide-y divide-shell-line">
                    <?php foreach ($categories as $row): ?>
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm">
                                <?= esc($row['name']) ?>
                                <span class="rs-help num ml-1"><?= (int) $row['n'] ?></span>
                            </span>

                            <?php if ((int) $row['n'] > 0): ?>
                                <span class="flex shrink-0 gap-1.5">
                                    <a href="<?= $csv('category=' . (int) $row['id']) ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">CSV</a>
                                    <a href="<?= $sql('category=' . (int) $row['id']) ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">SQL</a>
                                </span>
                            <?php else: ?>
                                <?php /* Nothing to export, so nothing to click —
                                         a button that returns an empty file is a
                                         button that wasted someone's time. */ ?>
                                <span class="rs-help">empty</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- ============================== by occasion ==================== -->
        <section class="border border-shell-line bg-white p-5 lg:col-span-2">
            <h2 class="rs-eyebrow rs-eyebrow--plain">By occasion</h2>
            <p class="rs-help mt-2">
                Every product in the occasion, with all of its variants.
            </p>

            <?php if ($occasions === []): ?>
                <p class="rs-help mt-3">No occasions yet.</p>
            <?php else: ?>
                <ul class="mt-4 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($occasions as $row): ?>
                        <li class="flex items-center justify-between gap-3 border-b border-shell-line py-2.5">
                            <span class="text-sm">
                                <?= esc($row['name']) ?>
                                <span class="rs-help num ml-1"><?= (int) $row['n'] ?></span>
                            </span>

                            <?php if ((int) $row['n'] > 0): ?>
                                <span class="flex shrink-0 gap-1.5">
                                    <a href="<?= $csv('occasion=' . (int) $row['id']) ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">CSV</a>
                                    <a href="<?= $sql('occasion=' . (int) $row['id']) ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">SQL</a>
                                </span>
                            <?php else: ?>
                                <span class="rs-help">empty</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
