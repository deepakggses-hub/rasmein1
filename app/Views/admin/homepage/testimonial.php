<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $quote === null;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Homepage',
    'heading'    => $isNew ? 'New testimonial' : 'Edit testimonial',
    'actions'    => '<a href="' . site_url('admin/homepage') . '" class="rs-btn rs-btn--outline rs-btn--sm">Back to homepage</a>',
]) ?>

<form method="post" enctype="multipart/form-data" class="px-5 py-6 lg:px-8"
      action="<?= $isNew ? site_url('admin/homepage/testimonials') : site_url('admin/homepage/testimonials/' . $quote['id']) ?>">
    <?= csrf_field() ?>

    <div class="grid max-w-4xl gap-6 lg:grid-cols-[1fr_16rem] lg:items-start">
        <section class="border border-shell-line bg-white p-5">
            <label class="block">
                <span class="rs-label">What they said <span class="text-bad">*</span></span>
                <textarea name="quote" class="rs-textarea" rows="4" required maxlength="600"><?= esc(old('quote') ?? $quote['quote'] ?? '') ?></textarea>
                <span class="rs-help">Quotation marks are added by the design — leave them out.</span>
            </label>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Name <span class="text-bad">*</span></span>
                    <input type="text" name="author" class="rs-input" required maxlength="120"
                           value="<?= $v('author', $quote['author'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Occasion and place</span>
                    <input type="text" name="role" class="rs-input" maxlength="160"
                           placeholder="Wedding · Udaipur" value="<?= $v('role', $quote['role'] ?? '') ?>">
                </label>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <label class="block">
                    <span class="rs-label">Stars</span>
                    <select name="rating" class="rs-select">
                        <?php foreach ([5, 4, 3, 2, 1] as $stars): ?>
                            <option value="<?= $stars ?>" <?= (int) (old('rating') ?? $quote['rating'] ?? 5) === $stars ? 'selected' : '' ?>>
                                <?= str_repeat('★', $stars) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($quote['sort_order'] ?? 0)) ?>">
                </label>

                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $quote['is_active']) ? 'checked' : '' ?>>
                    <span>Show on the homepage</span>
                </label>

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                    <?= $isNew ? 'Add testimonial' : 'Save' ?>
                </button>
            </section>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/homepage/testimonials/' . $quote['id'] . '/delete') ?>"
              data-confirm="Remove this testimonial?"
              data-confirm-detail="It disappears from the homepage. Nothing else is affected."
              data-confirm-action="Remove it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this testimonial</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
