<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $occasion === null;
$v  = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$dt = static fn (?string $x): string => $x !== null && $x !== '' ? date('Y-m-d\TH:i', strtotime($x)) : '';
$oldPicks = old('products');
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Occasion',
    'heading'    => $isNew ? 'New occasion' : $occasion['name'],
    'subheading' => $isNew ? null : 'Lives at /' . $occasion['slug'],
    'actions'    => '<a href="' . site_url('admin/occasions') . '" class="rs-btn rs-btn--outline rs-btn--sm">All occasions</a>'
        . ($isNew ? '' : '<a href="' . site_url((string) $occasion['slug']) . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View</a>'),
]) ?>

<form method="post" enctype="multipart/form-data" class="px-5 py-6 lg:px-8"
      action="<?= $isNew ? site_url('admin/occasions') : site_url('admin/occasions/' . $occasion['id']) ?>">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
        <div class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">The occasion</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Name <span class="text-bad">*</span></span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               placeholder="Diwali" value="<?= $v('name', $occasion['name'] ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Web address</span>
                        <div class="flex items-center">
                            <span class="border border-r-0 border-shell-line bg-shell-deep px-2 py-2 font-mono text-xs text-ink-muted">/</span>
                            <input type="text" name="slug" class="rs-input font-mono text-xs" maxlength="160"
                                   value="<?= $v('slug', $occasion['slug'] ?? '') ?>">
                        </div>
                        <span class="rs-help">Blank builds it from the name.</span>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Description</span>
                        <textarea name="description" class="rs-textarea" rows="3"
                                  placeholder="Hampers and boxes for the festival of lights."><?= esc(old('description') ?? $occasion['description'] ?? '') ?></textarea>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Image</span>
                        <input type="file" name="image" class="rs-input" accept="image/jpeg,image/png,image/webp">
                    </label>
                </div>
            </section>

            <!-- ========================= tagging ========================= -->
            <section class="border border-shell-line bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Products in this occasion</h2>
                    <div class="flex items-center gap-3 text-xs">
                        <input type="search" class="rs-input w-44 py-1 text-xs" placeholder="Filter by name or SKU"
                               data-tag-filter aria-label="Filter products">
                        <span class="num text-ink-muted"><span data-tag-count>0</span> tagged</span>
                    </div>
                </div>

                <?php if ($products === []): ?>
                    <p class="px-5 py-8 text-sm text-ink-muted">No products exist yet.</p>
                <?php else: ?>
                    <div class="max-h-[28rem] overflow-y-auto">
                        <ul class="divide-y divide-shell-line" data-tag-list>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $on = $oldPicks !== null
                                    ? in_array((string) $product->id, array_map('strval', (array) $oldPicks), true)
                                    : in_array((int) $product->id, $tagged, true);
                                ?>
                                <li data-tag-row
                                    data-search="<?= esc(mb_strtolower($product->name . ' ' . $product->sku . ' ' . ($product->category_name ?? '')), 'attr') ?>">
                                    <label class="flex items-center gap-3 px-5 py-2 hover:bg-shell">
                                        <input type="checkbox" name="products[]" value="<?= (int) $product->id ?>"
                                               class="accent-mulberry" data-tag <?= $on ? 'checked' : '' ?>>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm"><?= esc($product->name) ?></span>
                                            <span class="num block font-mono text-[0.625rem] text-ink-muted">
                                                <?= esc($product->sku) ?>
                                                <?php if (! empty($product->category_name)): ?>
                                                    · <?= esc($product->category_name) ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                        <?php if (! $product->is_active): ?>
                                            <span class="rs-badge rs-badge--out">Hidden</span>
                                        <?php endif; ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <p class="rs-help border-t border-shell-line px-5 py-2.5">
                        Unticking removes the product from this occasion. It is not deleted, and its
                        categories are untouched.
                    </p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">When it runs</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Starts</span>
                    <input type="datetime-local" name="starts_at" class="rs-input"
                           value="<?= esc(old('starts_at') ?? $dt($occasion['starts_at'] ?? null), 'attr') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Ends</span>
                    <input type="datetime-local" name="ends_at" class="rs-input"
                           value="<?= esc(old('ends_at') ?? $dt($occasion['ends_at'] ?? null), 'attr') ?>">
                </label>
                <p class="rs-help mt-2">
                    Leave both blank for an occasion that runs all year. Outside its dates the page
                    is hidden rather than shown empty.
                </p>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Visibility</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $occasion['is_active']) ? 'checked' : '' ?>>
                    <span>Live on the storefront</span>
                </label>
                <label class="mt-3 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_featured" value="1" class="accent-mulberry"
                           <?= (! $isNew && $occasion['is_featured']) ? 'checked' : '' ?>>
                    <span>Featured on the homepage</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($occasion['sort_order'] ?? 0)) ?>">
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Search listing</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Meta title</span>
                    <input type="text" name="meta_title" class="rs-input" maxlength="191"
                           value="<?= $v('meta_title', $occasion['meta_title'] ?? '') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Meta description</span>
                    <input type="text" name="meta_description" class="rs-input" maxlength="255"
                           value="<?= $v('meta_description', $occasion['meta_description'] ?? '') ?>">
                </label>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create occasion' : 'Save occasion' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/occasions/' . $occasion['id'] . '/delete') ?>"
              data-confirm="Remove &ldquo;<?= esc($occasion['name'], 'attr') ?>&rdquo;?"
              data-confirm-detail="The occasion page disappears. Its products are not deleted and keep their categories."
              data-confirm-action="Remove it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this occasion</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
