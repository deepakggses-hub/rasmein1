<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * The homepage, in the order it appears on screen.
 *
 * Sequenced deliberately: someone editing the homepage is thinking about the
 * page top to bottom, not about an alphabetical list of setting keys.
 */
$val = static fn (string $k): string => (string) (old($k) ?? ($settings[$k]['value'] ?? ''));
$lbl = static fn (string $k): string => (string) ($settings[$k]['label'] ?? $k);
$hlp = static fn (string $k): string => (string) ($settings[$k]['description'] ?? '');
$long = ['home_philosophy_body', 'home_signup_body'];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Homepage',
    'subheading' => 'Headings, hero slides, testimonials and the gallery.',
    'actions'    => '<a href="' . site_url('/') . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View the homepage</a>',
]) ?>

<?php if ($missing > 0): ?>
    <div class="px-5 pt-6 lg:px-8">
        <section class="border-2 border-brass bg-brass-soft/25 p-5">
            <h2 class="font-display text-lg font-semibold"><?= (int) $missing ?> heading(s) are not installed.</h2>
            <p class="rs-help mt-2">
                They arrive with the seed data. Until they exist the homepage falls back to its
                shipped wording, and editing here has nothing to write to.
            </p>
            <form method="post" action="<?= site_url('admin/homepage/restore') ?>" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="rs-btn rs-btn--primary">Install them now</button>
            </form>
        </section>
    </div>
<?php endif; ?>

<div class="px-5 py-6 lg:px-8">

    <!-- ========================= images and slides ========================= -->
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Pictures on the homepage</h2>
        <p class="rs-help mt-2 max-w-2xl">
            These are banners, so they can be scheduled and reordered like any other.
            A slot with nothing in it simply does not appear on the page &mdash; the section
            is hidden rather than shown empty.
        </p>

        <ul class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ([
                'home_hero'    => ['Hero slides', 'Two or more turn the hero into a slider.'],
                'home_feature' => ['Feature band', 'The full-width picture midway down.'],
                'home_client'  => ['Client logos', 'A logo image, or just a name if you leave the image blank.'],
                'home_gallery' => ['Bespoke gallery', 'The grid of square photographs.'],
            ] as $slot => [$name, $note]): ?>
                <?php $count = (int) ($slots[$slot] ?? 0); ?>
                <li class="border <?= $count === 0 ? 'border-shell-line' : 'border-brass' ?> p-4">
                    <p class="text-sm font-semibold"><?= esc($name) ?></p>
                    <p class="num mt-1 font-display text-2xl <?= $count === 0 ? 'text-ink-muted' : 'text-mulberry' ?>">
                        <?= $count ?>
                    </p>
                    <p class="rs-help"><?= esc($note) ?></p>
                    <a href="<?= site_url('admin/banners/new') ?>" class="rs-link mt-2 block text-xs text-mulberry">
                        <?= $count === 0 ? 'Add the first' : 'Add another' ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= site_url('admin/banners') ?>" class="rs-btn rs-btn--outline rs-btn--sm mt-5">Manage all banners</a>
    </section>

    <!-- ============================ the wording =========================== -->
    <form method="post" action="<?= site_url('admin/homepage') ?>" class="mt-6 space-y-6">
        <?= csrf_field() ?>

        <?php foreach ($sections as $sectionName => $keys): ?>
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain"><?= esc($sectionName) ?></h2>
                <div class="mt-4 grid gap-4 <?= count($keys) > 2 ? '' : 'sm:grid-cols-2' ?>">
                    <?php foreach ($keys as $key): ?>
                        <label class="<?= in_array($key, $long, true) ? 'sm:col-span-2' : '' ?>">
                            <span class="rs-label"><?= esc($lbl($key)) ?></span>
                            <?php if (in_array($key, $long, true)): ?>
                                <textarea name="<?= esc($key, 'attr') ?>" class="rs-textarea" rows="5"
                                          maxlength="4000"><?= esc($val($key)) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= esc($key, 'attr') ?>" class="rs-input"
                                       maxlength="255" value="<?= esc($val($key), 'attr') ?>">
                            <?php endif; ?>
                            <?php if ($hlp($key) !== ''): ?>
                                <span class="rs-help"><?= esc($hlp($key)) ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rs-btn rs-btn--primary">Save the wording</button>
            <span class="rs-help">
                Leave a heading blank to hide that whole section. The last two words of each
                headline are set in gold italic automatically.
            </span>
        </div>
    </form>

    <!-- =========================== testimonials =========================== -->
    <section class="mt-6 border border-shell-line bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Testimonials</h2>
            <a href="<?= site_url('admin/homepage/testimonials/new') ?>" class="rs-btn rs-btn--primary rs-btn--sm">
                New testimonial
            </a>
        </div>

        <?php if ($testimonials === []): ?>
            <p class="px-5 py-8 text-sm text-ink-muted">
                None yet. The testimonials section is hidden on the homepage until there is at
                least one.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($testimonials as $quote): ?>
                    <li class="flex flex-wrap items-start gap-4 px-5 py-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center bg-brass-soft font-display text-sm">
                            <?= esc(mb_strtoupper(mb_substr((string) $quote['author'], 0, 1))) ?>
                        </span>
                        <div class="min-w-52 flex-1">
                            <p class="text-sm text-ink-soft">&ldquo;<?= esc(rs_excerpt($quote['quote'], 120)) ?>&rdquo;</p>
                            <p class="mt-1 text-xs">
                                <span class="font-medium"><?= esc($quote['author']) ?></span>
                                <?php if (! empty($quote['role'])): ?>
                                    <span class="text-ink-muted">&middot; <?= esc($quote['role']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="num text-xs text-brass"><?= str_repeat('★', (int) $quote['rating']) ?></span>
                        <span class="rs-badge <?= $quote['is_active'] ? 'rs-badge--soft' : 'rs-badge--out' ?>">
                            <?= $quote['is_active'] ? 'Live' : 'Hidden' ?>
                        </span>
                        <a href="<?= site_url('admin/homepage/testimonials/' . $quote['id']) ?>"
                           class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
