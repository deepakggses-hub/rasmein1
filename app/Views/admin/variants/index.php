<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Variants for one product: a row each, editable in place.
 *
 * One form for the lot rather than a save button per row. Someone repricing a
 * colour range is changing four numbers, not making four decisions.
 *
 * @var \App\Entities\Product $product
 * @var array<int, array<string, mixed>> $variants
 * @var array<string, array<int, array<string, mixed>>> $choosable
 * @var array<int, int> $inCarts
 */
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Variants',
    'subheading' => $product->name,
    'actions'    => '<a href="' . site_url('admin/products/' . $product->id . '/edit')
        . '" class="rs-btn rs-btn--outline rs-btn--sm">Back to the product</a>'
        . ' <a href="' . site_url('product/' . $product->slug) . '" target="_blank" rel="noopener"'
        . ' class="rs-btn rs-btn--outline rs-btn--sm">View it</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <?php if ($variants === []): ?>
        <p class="border border-shell-line bg-white px-5 py-10 text-center text-sm text-ink-muted">
            No variants yet. Build one from this product's attributes below.
        </p>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/products/' . $product->id . '/variants') ?>"
              enctype="multipart/form-data">
            <?= csrf_field() ?>

            <p class="rs-help mb-4 max-w-3xl">
                Leave a price blank and the variant follows the product's
                <strong><?= esc($product->formattedPrice()) ?></strong>. Same for the picture and the
                description &mdash; only fill them in where this variant actually differs.
            </p>

            <div class="overflow-x-auto border border-shell-line bg-white">
                <table class="w-full min-w-[54rem] text-sm">
                    <thead class="border-b border-shell-line bg-shell-deep/40 text-left">
                        <tr>
                            <th class="px-3 py-2.5 font-medium">Variant</th>
                            <th class="px-3 py-2.5 font-medium">Price</th>
                            <th class="px-3 py-2.5 font-medium">Was</th>
                            <th class="px-3 py-2.5 font-medium">Stock</th>
                            <th class="px-3 py-2.5 font-medium">Picture</th>
                            <th class="px-3 py-2.5 text-center font-medium" title="Opens by default">Default</th>
                            <th class="px-3 py-2.5 text-center font-medium">On</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-shell-line">
                        <?php foreach ($variants as $variant): ?>
                            <?php $id = (int) $variant['id']; ?>
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <input type="text" name="variant[<?= $id ?>][label]"
                                           class="rs-input" maxlength="191"
                                           value="<?= esc($variant['label'], 'attr') ?>">
                                    <p class="rs-help mt-1 font-mono text-[0.625rem]">
                                        <?= esc($variant['sku']) ?>
                                        <span class="mx-1 text-brass" aria-hidden="true">&middot;</span>
                                        /<?= esc($variant['variant_key']) ?>
                                    </p>

                                    <?php /* Kept out of the row: a description is a
                                             paragraph, and a column for it would
                                             squash everything else. */ ?>
                                    <details class="mt-2">
                                        <summary class="rs-help cursor-pointer">
                                            Description<?= $variant['description'] ? ' (set)' : '' ?>
                                        </summary>
                                        <textarea name="variant[<?= $id ?>][description]" class="rs-textarea mt-2"
                                                  rows="3" maxlength="2000"
                                                  placeholder="Blank follows the product."><?= esc((string) $variant['description']) ?></textarea>
                                    </details>
                                </td>

                                <td class="px-3 py-3">
                                    <input type="number" name="variant[<?= $id ?>][price]" step="0.01" min="0"
                                           class="rs-input num w-28"
                                           placeholder="<?= esc((string) round((float) $product->price), 'attr') ?>"
                                           value="<?= $variant['price'] !== null ? esc((string) round((float) $variant['price'], 2), 'attr') : '' ?>">
                                </td>

                                <td class="px-3 py-3">
                                    <input type="number" name="variant[<?= $id ?>][compare_at_price]" step="0.01" min="0"
                                           class="rs-input num w-28"
                                           value="<?= $variant['compare_at_price'] !== null ? esc((string) round((float) $variant['compare_at_price'], 2), 'attr') : '' ?>">
                                </td>

                                <td class="px-3 py-3">
                                    <input type="number" name="variant[<?= $id ?>][stock_qty]" min="0"
                                           class="rs-input num w-20"
                                           value="<?= (int) $variant['stock_qty'] ?>">
                                    <?php if (! empty($inCarts[$id])): ?>
                                        <p class="rs-help mt-1">in <?= (int) $inCarts[$id] ?> basket(s)</p>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 py-3">
                                    <?php if (! empty($variant['image'])): ?>
                                        <img src="<?= rs_image((string) $variant['image'], 'thumb') ?>" alt=""
                                             class="mb-1.5 h-12 w-12 border border-shell-line object-cover">
                                    <?php endif; ?>
                                    <input type="file" name="image_<?= $id ?>" class="rs-input text-xs"
                                           accept="image/jpeg,image/png,image/webp">
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <?php /* A radio, not a checkbox — exactly one
                                             variant opens the page, and the control
                                             should say so. */ ?>
                                    <input type="radio" name="is_default" value="<?= $id ?>" class="accent-mulberry"
                                           <?= (int) $variant['is_default'] === 1 ? 'checked' : '' ?>>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <input type="checkbox" name="variant[<?= $id ?>][is_active]" value="1"
                                           class="accent-mulberry"
                                           <?= (int) $variant['is_active'] === 1 ? 'checked' : '' ?>>
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <input type="hidden" name="variant[<?= $id ?>][sort_order]"
                                           value="<?= (int) $variant['sort_order'] ?>">
                                    <?php if (empty($inCarts[$id])): ?>
                                        <button type="submit" class="text-ink-muted hover:text-bad"
                                                formaction="<?= site_url('admin/products/' . $product->id . '/variants/' . $id . '/delete') ?>"
                                                formnovalidate
                                                aria-label="Remove <?= esc($variant['label'], 'attr') ?>">&times;</button>
                                    <?php else: ?>
                                        <span class="rs-help" title="In a basket — switch it off instead">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="rs-btn rs-btn--primary mt-5">Save all variants</button>
        </form>
    <?php endif; ?>

    <!-- ============================ add a combination ==================== -->
    <section class="mt-8 max-w-2xl border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Add a variant</h2>

        <?php if ($choosable === []): ?>
            <p class="rs-help mt-3">
                This product has no attributes a customer chooses between. Tick some on the
                <a href="<?= site_url('admin/products/' . $product->id . '/edit') ?>"
                   class="rs-link text-mulberry">product form</a> first &mdash; and make sure the attribute
                itself is marked &ldquo;customer chooses&rdquo; under
                <a href="<?= site_url('admin/attributes') ?>" class="rs-link text-mulberry">Attributes</a>.
            </p>
        <?php else: ?>
            <form method="post" action="<?= site_url('admin/products/' . $product->id . '/variants/new') ?>"
                  class="mt-4">
                <?= csrf_field() ?>

                <?php foreach ($choosable as $attribute => $values): ?>
                    <div class="mt-4">
                        <span class="rs-label"><?= esc($attribute) ?></span>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($values as $value): ?>
                                <li>
                                    <label class="flex cursor-pointer items-center gap-2 border border-shell-line px-2.5 py-1.5 text-sm">
                                        <input type="checkbox" name="values[]" value="<?= (int) $value['id'] ?>"
                                               class="accent-mulberry">
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

                <p class="rs-help mt-4">One value from each row makes one combination.</p>
                <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm mt-3">Add this combination</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
