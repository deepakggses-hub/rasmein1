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
                        <span class="rs-label">Material</span>
                        <input type="text" name="material" class="rs-input" maxlength="80" list="rs-materials"
                               placeholder="Brass" value="<?= $v('material', $product->material ?? '') ?>">
                        <?php /* A datalist rather than a select: a shop can type
                                 something new without an administrator creating it
                                 first, and the shop filter is built from whatever
                                 is actually in use. */ ?>
                        <datalist id="rs-materials">
                            <?php foreach ($materials ?? [] as $known): ?>
                                <option value="<?= esc($known, 'attr') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <span class="rs-help">Shown as a filter on the shop.</span>
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

            <!-- ============================ the panels ==================== -->
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">What the product page shows</h2>
                <p class="rs-help mt-2 max-w-2xl">
                    Each of these becomes an expandable panel on the product page. Leave one
                    blank and that panel does not appear &mdash; better than an empty heading a
                    customer opens for nothing.
                </p>

                <div class="mt-5 grid gap-4">
                    <label>
                        <span class="rs-label">Eyebrow label</span>
                        <input type="text" name="eyebrow_label" class="rs-input" maxlength="60"
                               placeholder="Signature hamper"
                               value="<?= $v('eyebrow_label', $product->eyebrow_label ?? '') ?>">
                        <span class="rs-help">
                            Shown above the title after the category, as
                            <span class="font-mono">WEDDING &middot; SIGNATURE HAMPER</span>.
                        </span>
                    </label>

                    <label>
                        <span class="rs-label">The composition</span>
                        <textarea name="composition" class="rs-textarea" rows="4" maxlength="2000"
                                  placeholder="Banarasi silk stole (100% mulberry silk), two hand-cast brass diyas…"><?= esc(old('composition') ?? $product->composition ?? '') ?></textarea>
                        <span class="rs-help">What is inside. Opens by default on the product page.</span>
                    </label>

                    <label>
                        <span class="rs-label">Packaging &amp; delivery</span>
                        <textarea name="packaging_note" class="rs-textarea" rows="3" maxlength="2000"><?= esc(old('packaging_note') ?? $product->packaging_note ?? '') ?></textarea>
                    </label>

                    <label>
                        <span class="rs-label">Care instructions</span>
                        <textarea name="care_note" class="rs-textarea" rows="3" maxlength="2000"><?= esc(old('care_note') ?? $product->care_note ?? '') ?></textarea>
                    </label>

                    <label>
                        <span class="rs-label">Personalise this item</span>
                        <textarea name="personalisation_note" class="rs-textarea" rows="3" maxlength="2000"
                                  placeholder="Hand-lettered cards, monogramming and bulk ordering — write to us."><?= esc(old('personalisation_note') ?? $product->personalisation_note ?? '') ?></textarea>
                    </label>
                </div>

                <div class="mt-6 grid gap-4 border-t border-shell-line pt-5 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Rating shown (0&ndash;5)</span>
                        <input type="number" name="rating_average" class="rs-input num" min="0" max="5" step="0.1"
                               value="<?= $v('rating_average', (string) ($product->rating_average ?? '')) ?>">
                    </label>
                    <label>
                        <span class="rs-label">Number of reviews shown</span>
                        <input type="number" name="review_count" class="rs-input num" min="0" max="1000000"
                               value="<?= $v('review_count', (string) ($product->review_count ?? '')) ?>">
                    </label>
                    <p class="rs-help sm:col-span-2">
                        <strong>These are typed in, not calculated.</strong> There is no customer
                        review system yet, so whatever you enter is what shows. Leave both blank
                        and the stars do not appear at all.
                    </p>
                </div>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Images</h2>

                <?php if ($images !== []): ?>
                    <?php /* A list rather than a tight grid, because each image
                             needs an alt field beside it — the column existed
                             all along but there was never a field, so every
                             product image shipped with an empty alt. */ ?>
                    <ul class="mt-4 divide-y divide-shell-line border border-shell-line">
                        <?php foreach ($images as $image): ?>
                            <li class="flex flex-wrap items-center gap-3 p-3 <?= (int) $image['is_primary'] === 1 ? 'bg-brass-soft/20' : '' ?>">
                                <span class="block h-16 w-16 shrink-0 overflow-hidden bg-shell-deep">
                                    <img src="<?= rs_image($image['path'], 'products') ?>" alt=""
                                         loading="lazy" decoding="async" class="h-full w-full object-cover">
                                </span>

                                <label class="min-w-56 flex-1">
                                    <span class="sr-only">Describe this photograph</span>
                                    <input type="text" name="image_alt[<?= (int) $image['id'] ?>]"
                                           class="rs-input <?= trim((string) ($image['alt_text'] ?? '')) === '' ? 'border-brass' : '' ?>"
                                           maxlength="191"
                                           placeholder="Describe what the photograph shows"
                                           value="<?= esc((string) ($image['alt_text'] ?? ''), 'attr') ?>">
                                </label>

                                <div class="flex shrink-0 items-center gap-2">
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
                            <strong>Upload the largest version you have.</strong> Six sizes are
                            generated automatically, plus a WebP of each, and the visitor's browser
                            picks the smallest that still looks sharp on their screen &mdash; so a
                            big original costs nothing extra to serve. Around 1600&ndash;2400px on
                            the long edge is ideal.
                        </span>
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
                <h2 class="rs-eyebrow rs-eyebrow--plain">Occasions</h2>
                <?php if ($occasions === []): ?>
                    <p class="rs-help mt-3">
                        None created yet.
                        <a href="<?= site_url('admin/occasions/new') ?>" class="rs-link">Add one</a>.
                    </p>
                <?php else: ?>
                    <ul class="mt-3 space-y-2">
                        <?php foreach ($occasions as $occasion): ?>
                            <?php
                            $on = old('occasions') !== null
                                ? in_array((string) $occasion['id'], array_map('strval', (array) old('occasions')), true)
                                : in_array((int) $occasion['id'], $taggedOccasions, true);
                            ?>
                            <li>
                                <label class="flex items-center gap-2.5 text-sm">
                                    <input type="checkbox" name="occasions[]" value="<?= (int) $occasion['id'] ?>"
                                           class="accent-mulberry" <?= $on ? 'checked' : '' ?>>
                                    <span><?= esc($occasion['name']) ?></span>
                                    <?php if (! $occasion['is_active']): ?>
                                        <span class="rs-badge rs-badge--out">Off</span>
                                    <?php endif; ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="rs-help mt-3">A product can belong to several. This does not affect its category.</p>
                <?php endif; ?>
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

    <?php /* ============================ attributes ============================ */ ?>
    <section class="mt-6 border border-shell-line bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Attributes</h2>

            <?php if (! $isNew): ?>
                <?php /* Variants are built FROM these ticks, so the link lives
                         here rather than in a menu somewhere else. */ ?>
                <a href="<?= site_url('admin/products/' . $product->id . '/variants') ?>"
                   class="rs-btn rs-btn--outline rs-btn--sm">
                    Variants &amp; pricing
                    <?php if (! empty($variantCount)): ?>
                        <span class="num ml-1">(<?= (int) $variantCount ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
        <p class="rs-help mt-2 max-w-2xl">
            Tick everything this piece is available in. Values come from
            <a href="<?= site_url('admin/attributes') ?>" class="rs-link text-mulberry">Attributes</a>,
            so the same colour is never spelt two ways across the catalogue.
        </p>

        <?php if (($attributes ?? []) === []): ?>
            <p class="rs-help mt-4">No attributes are set up yet.</p>
        <?php else: ?>
            <div class="mt-5 grid gap-5">
                <?php foreach ($attributes as $attribute): ?>
                    <?php if ($attribute['values'] === []) { continue; } ?>
                    <div>
                        <span class="rs-label">
                            <?= esc($attribute['name']) ?>
                            <?php if ((int) $attribute['is_selectable'] === 1): ?>
                                <span class="rs-help">— the customer picks one</span>
                            <?php endif; ?>
                        </span>

                        <ul class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($attribute['values'] as $value): ?>
                                <?php $on = in_array((int) $value['id'], $productValueIds ?? [], true); ?>
                                <li>
                                    <label class="flex cursor-pointer items-center gap-2 border px-2.5 py-1.5 text-sm
                                                  <?= $on ? 'border-mulberry bg-brass-soft/30' : 'border-shell-line' ?>">
                                        <input type="checkbox" name="attribute_values[]" class="accent-mulberry"
                                               value="<?= (int) $value['id'] ?>" <?= $on ? 'checked' : '' ?>>
                                        <?php if (! empty($value['swatch_hex'])): ?>
                                            <span class="h-4 w-4 rounded-full border border-shell-line"
                                                  style="background: <?= esc($value['swatch_hex'], 'attr') ?>"></span>
                                        <?php endif; ?>
                                        <span><?= esc($value['label']) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

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
