<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * A banner form that shows only the fields its slot actually renders.
 *
 * A gallery photograph has no heading, no buttons and no schedule, so it is not
 * asked for any. A field that never appears anywhere is worse than a missing
 * one: someone fills it in, nothing happens, and nothing tells them why.
 *
 * @var array $meta  The slot definition, including its `fields` list
 */
$isNew  = $banner === null;
$v      = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$dt     = static fn (?string $x): string => $x !== null && $x !== '' ? date('Y-m-d\TH:i', strtotime($x)) : '';
$fields = $meta['fields'];
$has    = static fn (string $f): bool => in_array($f, $fields, true);
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Banners',
    'heading'    => $isNew ? 'Add to ' . strtolower($meta['label']) : ($banner['title'] ?: 'Picture only'),
    'subheading' => $meta['label'],
    'actions'    => '<a href="' . site_url('admin/banners/' . $meta['key']) . '" class="rs-btn rs-btn--outline rs-btn--sm">Back</a>',
]) ?>

<form method="post" enctype="multipart/form-data" class="px-5 py-6 lg:px-8"
      action="<?= $isNew ? site_url('admin/banners') : site_url('admin/banners/' . $banner['id']) ?>">
    <?= csrf_field() ?>
    <?php /* The slot is the page, not a choice. Still posted and still
             validated, because a hidden field is a posted value like any other. */ ?>
    <input type="hidden" name="position" value="<?= esc($slot, 'attr') ?>">

    <div class="grid max-w-4xl gap-6 lg:grid-cols-[1fr_16rem] lg:items-start">
        <div class="space-y-5">

            <!-- ============================= picture ======================= -->
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">The picture</h2>

                <?php if (! $isNew && ! empty($banner['image'])): ?>
                    <div class="mt-4 overflow-hidden border border-shell-line bg-shell-deep">
                        <img src="<?= rs_image($banner['image'], 'banners') ?>" alt=""
                             class="max-h-56 w-full object-contain">
                    </div>
                <?php endif; ?>

                <label class="mt-4 block">
                    <span class="rs-label">Image<?= $isNew ? ' <span class="text-bad">*</span>' : '' ?></span>
                    <input type="file" name="image" class="rs-input" accept="image/jpeg,image/png,image/webp"
                           <?= $isNew && ! $has('name') ? 'required' : '' ?>>
                    <span class="rs-help">
                        <?= esc($meta['ratio']) ?>. Six sizes and a WebP of each are made
                        automatically, so upload the largest version you have.
                    </span>
                </label>

                <?php if ($has('name')): ?>
                    <label class="mt-4 block">
                        <span class="rs-label">Name</span>
                        <input type="text" name="title" class="rs-input" maxlength="191"
                               placeholder="Brand 01" value="<?= $v('title', $banner['title'] ?? '') ?>">
                        <span class="rs-help">
                            Used when there is no logo image — the name is set in the display
                            face instead, as in the design.
                        </span>
                    </label>
                <?php endif; ?>

                <?php if ($has('link') && ! $has('buttons')): ?>
                    <label class="mt-4 block">
                        <span class="rs-label">Where it links</span>
                        <div class="flex items-center">
                            <span class="border border-r-0 border-shell-line bg-shell-deep px-2 py-2 font-mono text-xs text-ink-muted">/</span>
                            <input type="text" name="link_url" class="rs-input font-mono text-xs" maxlength="255"
                                   placeholder="shop" value="<?= $v('link_url', ltrim((string) ($banner['link_url'] ?? ''), '/')) ?>">
                        </div>
                        <span class="rs-help">Optional. A path on this site.</span>
                    </label>
                <?php elseif ($has('link')): ?>
                    <p class="rs-help mt-4">
                        Where the picture links is the <strong>first button's link</strong> below.
                        With no text, the whole image becomes that link.
                    </p>
                <?php endif; ?>

                <?php /* Alt text is not asked for here. It is managed for every
                         image on the site at once under Content → Image alt text,
                         which is where it actually gets filled in. */ ?>
                <p class="rs-help mt-4">
                    Descriptions for screen readers live under
                    <a href="<?= site_url('admin/media') ?>" class="rs-link text-mulberry">Image alt text</a>,
                    where every image on the site is listed together.
                </p>
            </section>

            <!-- ============================== words ======================= -->
            <?php if ($has('text') || $has('title')): ?>
                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Words over the picture</h2>
                    <p class="rs-help mt-2 max-w-2xl">
                        <strong>Leave these blank</strong> and the picture is shown whole with the
                        whole thing linked — right when the words are already part of the artwork.
                        Type anything and a heading and buttons are laid over it instead.
                    </p>

                    <div class="mt-4 grid gap-4">
                        <?php if ($has('text')): ?>
                            <label>
                                <span class="rs-label">Small heading above the title</span>
                                <input type="text" name="eyebrow" class="rs-input" maxlength="60"
                                       placeholder="Wedding season" value="<?= $v('eyebrow', $banner['eyebrow'] ?? '') ?>">
                            </label>
                        <?php endif; ?>
                        <label>
                            <span class="rs-label">Title</span>
                            <input type="text" name="title" class="rs-input" maxlength="191"
                                   placeholder="Customize your Gift" value="<?= $v('title', $banner['title'] ?? '') ?>">
                            <span class="rs-help">The last two words are set in gold italic automatically.</span>
                        </label>
                        <label>
                            <span class="rs-label">Description</span>
                            <textarea name="subtitle" class="rs-textarea" rows="2" maxlength="255"><?= esc(old('subtitle') ?? $banner['subtitle'] ?? '') ?></textarea>
                        </label>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ============================= buttons ====================== -->
            <?php if ($has('buttons')): ?>
                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Buttons</h2>
                    <p class="rs-help mt-2 max-w-2xl">
                        Both optional — a button with no label is not drawn. They appear only when
                        there is text above.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label>
                            <span class="rs-label">First button</span>
                            <input type="text" name="cta_label" class="rs-input" maxlength="60"
                                   placeholder="Explore collection" value="<?= $v('cta_label', $banner['cta_label'] ?? '') ?>">
                        </label>
                        <label>
                            <span class="rs-label">…links to</span>
                            <div class="flex items-center">
                                <span class="border border-r-0 border-shell-line bg-shell-deep px-2 py-2 font-mono text-xs text-ink-muted">/</span>
                                <input type="text" name="link_url" class="rs-input font-mono text-xs" maxlength="255"
                                       placeholder="shop" value="<?= $v('link_url', ltrim((string) ($banner['link_url'] ?? ''), '/')) ?>">
                            </div>
                        </label>

                        <label>
                            <span class="rs-label">Second button</span>
                            <input type="text" name="cta_label_2" class="rs-input" maxlength="60"
                                   placeholder="Build your gift box" value="<?= $v('cta_label_2', $banner['cta_label_2'] ?? '') ?>">
                        </label>
                        <label>
                            <span class="rs-label">…links to</span>
                            <div class="flex items-center">
                                <span class="border border-r-0 border-shell-line bg-shell-deep px-2 py-2 font-mono text-xs text-ink-muted">/</span>
                                <input type="text" name="link_url_2" class="rs-input font-mono text-xs" maxlength="255"
                                       placeholder="build" value="<?= $v('link_url_2', ltrim((string) ($banner['link_url_2'] ?? ''), '/')) ?>">
                            </div>
                        </label>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Placement</h2>
                <p class="mt-3 text-sm font-medium"><?= esc($meta['label']) ?></p>
                <p class="rs-help"><?= esc($meta['ratio']) ?></p>

                <label class="mt-4 block">
                    <span class="rs-label">Order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($banner['sort_order'] ?? 0)) ?>">
                    <span class="rs-help">Lower numbers first.</span>
                </label>

                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $banner['is_active']) ? 'checked' : '' ?>>
                    <span>Live on the site</span>
                </label>
            </section>

            <?php if ($has('schedule')): ?>
                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">When</h2>
                    <label class="mt-4 block">
                        <span class="rs-label">Starts</span>
                        <input type="datetime-local" name="starts_at" class="rs-input"
                               value="<?= esc(old('starts_at') ?? $dt($banner['starts_at'] ?? null), 'attr') ?>">
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Ends</span>
                        <input type="datetime-local" name="ends_at" class="rs-input"
                               value="<?= esc(old('ends_at') ?? $dt($banner['ends_at'] ?? null), 'attr') ?>">
                    </label>
                    <p class="rs-help mt-2">Blank dates mean it runs until switched off.</p>
                </section>
            <?php endif; ?>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Add' : 'Save' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/banners/' . $banner['id'] . '/delete') ?>"
              data-confirm="Remove this?"
              data-confirm-detail="The image file is deleted too. If it was the only one here, that part of the page disappears."
              data-confirm-action="Remove it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
