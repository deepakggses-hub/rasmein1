<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/** @var \App\Entities\Product|null $product */
$isNew  = $product === null;
$action = $isNew ? site_url('admin/products') : site_url('admin/products/' . $product->id);
$v = static fn (string $field, $fallback = '') => esc((string) (old($field) ?? $fallback), 'attr');
$checked = static fn (string $field, bool $fallback): string => (old($field) !== null ? true : $fallback) ? 'checked' : '';
?>

<?= view('admin/partials/header', [
    'eyebrow' => 'Catalogue',
    'heading' => $isNew ? 'New product' : $product->name,
    'subheading' => $isNew ? null : 'SKU ' . $product->sku,
    'actions' => '<a href="' . site_url('admin/products') . '" class="rs-btn rs-btn--outline rs-btn--sm">All products</a>',
]) ?>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
        <div class="space-y-5">

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Basics</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="rs-label">Name <span class="text-bad">*</span></span>
                        <input type="text" name="name" class="rs-input" required maxlength="191"
                               value="<?= $v('name', $product->name ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">SKU <span class="text-bad">*</span></span>
                        <input type="text" name="sku" class="rs-input num" required maxlength="60"
                               value="<?= $v('sku', $product->sku ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">URL slug</span>
                        <input type="text" name="slug" class="rs-input" maxlength="200"
                               value="<?= $v('slug', $product->slug ?? '') ?>">
                        <span class="rs-help">Left blank, it is made from the name.</span>
                    </label>
                    <label>
                        <span class="rs-label">Category</span>
                        <select name="category_id" class="rs-select">
                            <option value="">Uncategorised</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category->id ?>"
                                    <?= (int) (old('category_id') ?? $product->category_id ?? 0) === (int) $category->id ? 'selected' : '' ?>>
                                    <?= esc($category->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="rs-label">Unit label</span>
                        <input type="text" name="unit_label" class="rs-input" maxlength="40" placeholder="250 g jar"
                               value="<?= $v('unit_label', $product->unit_label ?? '') ?>">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Short description</span>
                        <input type="text" name="short_description" class="rs-input" maxlength="255"
                               value="<?= $v('short_description', $product->short_description ?? '') ?>">
                        <span class="rs-help">One line, shown on cards and in search.</span>
                    </label>
                    <div class="sm:col-span-2">
                        <?= view('admin/partials/editor', [
                            'name'  => 'description',
                            'label' => 'Full description',
                            'value' => old('description') ?? $product->description ?? '',
                            'rows'  => 10,
                            'help'  => 'Shown on the product page. Formatting beyond the toolbar is '
                                . 'removed when you save.',
                        ]) ?>
                    </div>
                </div>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Images</h2>

                <?php if ($images !== []): ?>
                    <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                        <?php foreach ($images as $image): ?>
                            <li class="border <?= (int) $image['is_primary'] === 1 ? 'border-brass' : 'border-shell-line' ?>">
                                <span class="block aspect-square overflow-hidden bg-shell-deep">
                                    <img src="<?= esc(rs_image($image['path'], 'products'), 'attr') ?>" alt=""
                                         loading="lazy" class="h-full w-full object-cover">
                                </span>
                                <div class="flex items-center justify-between gap-1 p-1.5">
                                    <?php if ((int) $image['is_primary'] === 1): ?>
                                        <span class="rs-badge rs-badge--brass">Main</span>
                                    <?php else: ?>
                                        <button type="submit" form="img-primary-<?= (int) $image['id'] ?>"
                                                class="rs-link text-[0.625rem] text-ink-muted">Make main</button>
                                    <?php endif; ?>
                                    <button type="submit" form="img-del-<?= (int) $image['id'] ?>"
                                            class="text-ink-muted hover:text-bad" aria-label="Remove image">&times;</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <label class="mt-4 block">
                    <span class="rs-label">Add images</span>
                    <input type="file" name="images[]" class="rs-input" multiple
                           accept="image/jpeg,image/png,image/webp">
                    <span class="rs-help">
                        JPEG, PNG or WebP, up to <?= round($maxBytes / 1048576, 1) ?> MB each.
                        Wider than 2400px is scaled down. Files are re-encoded on upload, which
                        also strips any location data the camera recorded.
                    </span>
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Search listing</h2>
                <div class="mt-4 grid gap-4">
                    <label>
                        <span class="rs-label">Meta title</span>
                        <input type="text" name="meta_title" class="rs-input" maxlength="191"
                               value="<?= $v('meta_title', $product->meta_title ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Meta description</span>
                        <input type="text" name="meta_description" class="rs-input" maxlength="255"
                               value="<?= $v('meta_description', $product->meta_description ?? '') ?>">
                    </label>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Price</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Price <span class="text-bad">*</span></span>
                    <input type="number" name="price" class="rs-input num" step="0.01" min="0" required
                           value="<?= $v('price', (string) ($product->price ?? '')) ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Was</span>
                    <input type="number" name="compare_at_price" class="rs-input num" step="0.01" min="0"
                           value="<?= $v('compare_at_price', (string) ($product->compare_at_price ?? '')) ?>">
                    <span class="rs-help">Shown struck through, if higher than the price.</span>
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Stock</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="track_inventory" value="1" class="accent-mulberry"
                           <?= $checked('track_inventory', (bool) ($product->track_inventory ?? true)) ?>>
                    <span>Track stock</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Quantity</span>
                    <input type="number" name="stock_qty" class="rs-input num" min="0"
                           value="<?= $v('stock_qty', (string) ($product->stock_qty ?? 0)) ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Warn below</span>
                    <input type="number" name="low_stock_threshold" class="rs-input num" min="0"
                           value="<?= $v('low_stock_threshold', (string) ($product->low_stock_threshold ?? 10)) ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Weight (g)</span>
                    <input type="number" name="weight_grams" class="rs-input num" min="0"
                           value="<?= $v('weight_grams', (string) ($product->weight_grams ?? '')) ?>">
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Gifting</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_giftbox_eligible" value="1" class="accent-mulberry"
                           <?= $checked('is_giftbox_eligible', (bool) ($product->is_giftbox_eligible ?? true)) ?>>
                    <span>Can go in a gift box</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Compartments used</span>
                    <input type="number" name="giftbox_slots" class="rs-input num" min="1" max="24"
                           value="<?= $v('giftbox_slots', (string) ($product->giftbox_slots ?? 1)) ?>">
                    <span class="rs-help">How many slots one unit takes up in a box.</span>
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">How it sells</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Journey</span>
                    <select name="sale_mode" class="rs-select">
                        <?php foreach ([
                            'inherit'     => 'Follow the store setting',
                            'buy_now'     => 'Always Buy now',
                            'enquire_now' => 'Always quoted (Enquire)',
                        ] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= (old('sale_mode') ?? $product->sale_mode ?? 'inherit') === $k ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="rs-help">
                        A quoted item turns its whole basket into an enquiry.
                    </span>
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Visibility</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= $checked('is_active', (bool) ($product->is_active ?? true)) ?>>
                    <span>Live on the storefront</span>
                </label>
                <label class="mt-3 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_featured" value="1" class="accent-mulberry"
                           <?= $checked('is_featured', (bool) ($product->is_featured ?? false)) ?>>
                    <span>Featured on the homepage</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($product->sort_order ?? 0)) ?>">
                </label>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create product' : 'Save changes' ?>
            </button>
        </aside>
    </div>
</form>

<?php /* Separate forms, because HTML cannot nest them inside the one above. */ ?>
<?php if (! $isNew): ?>
    <?php foreach ($images as $image): ?>
        <form id="img-del-<?= (int) $image['id'] ?>" method="post" class="hidden"
              action="<?= site_url('admin/products/' . $product->id . '/images/' . $image['id'] . '/delete') ?>">
            <?= csrf_field() ?>
        </form>
        <form id="img-primary-<?= (int) $image['id'] ?>" method="post" class="hidden"
              action="<?= site_url('admin/products/' . $product->id . '/images/' . $image['id'] . '/primary') ?>">
            <?= csrf_field() ?>
        </form>
    <?php endforeach; ?>

    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/products/' . $product->id . '/delete') ?>"
              data-confirm="Remove this product? Past orders keep their record of it." data-confirm-action="Yes, do it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this product</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
