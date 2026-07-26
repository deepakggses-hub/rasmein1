<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $banner === null;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$dt = static fn (?string $x): string => $x !== null ? date('Y-m-d\TH:i', strtotime($x)) : '';
?>

<?= view('admin/partials/header', [
    'eyebrow' => 'Content',
    'heading' => $isNew ? 'New banner' : ($banner['title'] ?: 'Edit banner'),
    'actions' => '<a href="' . site_url('admin/banners') . '" class="rs-btn rs-btn--outline rs-btn--sm">All banners</a>',
]) ?>

<form method="post" enctype="multipart/form-data" class="px-5 py-6 lg:px-8"
      action="<?= $isNew ? site_url('admin/banners') : site_url('admin/banners/' . $banner['id']) ?>">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Wording</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Eyebrow</span>
                    <input type="text" name="eyebrow" class="rs-input" maxlength="60" placeholder="Build your own"
                           value="<?= $v('eyebrow', $banner['eyebrow'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Headline</span>
                    <input type="text" name="title" class="rs-input" maxlength="191"
                           value="<?= $v('title', $banner['title'] ?? '') ?>">
                    <span class="rs-help">Blank on the hero keeps the hand-set headline.</span>
                </label>
                <label class="sm:col-span-2">
                    <span class="rs-label">Subtitle</span>
                    <input type="text" name="subtitle" class="rs-input" maxlength="255"
                           value="<?= $v('subtitle', $banner['subtitle'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Button label</span>
                    <input type="text" name="cta_label" class="rs-input" maxlength="60"
                           value="<?= $v('cta_label', $banner['cta_label'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Button link</span>
                    <input type="text" name="link_url" class="rs-input" maxlength="255" placeholder="/build"
                           value="<?= $v('link_url', $banner['link_url'] ?? '') ?>">
                    <span class="rs-help">Must stay on this site — start with a slash.</span>
                </label>
                <label class="sm:col-span-2">
                    <span class="rs-label">Image</span>
                    <input type="file" name="image" class="rs-input" accept="image/jpeg,image/png,image/webp">
                </label>
                <label class="sm:col-span-2">
                    <span class="rs-label">Image description</span>
                    <input type="text" name="alt_text" class="rs-input" maxlength="191"
                           value="<?= $v('alt_text', $banner['alt_text'] ?? '') ?>">
                    <span class="rs-help">For screen readers, and shown if the image fails to load.</span>
                </label>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Placement</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Slot</span>
                    <select name="position" class="rs-select">
                        <?php foreach ([
                            'home_hero' => 'Homepage hero', 'home_strip' => 'Homepage strip',
                            'category_top' => 'Category top', 'gift_builder' => 'Gift builder',
                        ] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= (old('position') ?? $banner['position'] ?? 'home_hero') === $k ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($banner['sort_order'] ?? 0)) ?>">
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Schedule</h2>
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
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $banner['is_active']) ? 'checked' : '' ?>>
                    <span>Active</span>
                </label>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create banner' : 'Save banner' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/banners/' . $banner['id'] . '/delete') ?>"
              data-confirm="Remove this banner?" data-confirm-action="Yes, do it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this banner</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
