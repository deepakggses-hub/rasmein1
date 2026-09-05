<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Attributes and their values.
 *
 * Everything on one screen: an attribute without its values is meaningless, and
 * making someone click into each one to add a colour is a step for nothing.
 *
 * @var array<int, array<string, mixed>> $attributes
 * @var array<int, int> $usage
 */
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Attributes',
    'subheading' => 'Colour, size, shape and finish — and the values each one allows.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="grid max-w-6xl gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">

        <!-- ============================== existing ========================= -->
        <div class="space-y-5">
            <?php if ($attributes === []): ?>
                <p class="border border-shell-line bg-white px-5 py-12 text-center text-sm text-ink-muted">
                    No attributes yet. Add one on the right.
                </p>
            <?php endif; ?>

            <?php foreach ($attributes as $attribute): ?>
                <section class="border border-shell-line bg-white">
                    <!-- the attribute itself -->
                    <form method="post" action="<?= site_url('admin/attributes/save') ?>"
                          class="flex flex-wrap items-end gap-3 border-b border-shell-line p-5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $attribute['id'] ?>">

                        <label class="min-w-40 flex-1">
                            <span class="rs-label">Name</span>
                            <input type="text" name="name" class="rs-input" maxlength="80"
                                   value="<?= esc($attribute['name'], 'attr') ?>">
                        </label>

                        <label class="w-32">
                            <span class="rs-label">Code</span>
                            <input type="text" name="code" class="rs-input font-mono text-xs" maxlength="40"
                                   value="<?= esc($attribute['code'], 'attr') ?>">
                        </label>

                        <label class="w-32">
                            <span class="rs-label">Shown as</span>
                            <select name="input_type" class="rs-select">
                                <option value="text" <?= $attribute['input_type'] === 'text' ? 'selected' : '' ?>>Chip</option>
                                <option value="swatch" <?= $attribute['input_type'] === 'swatch' ? 'selected' : '' ?>>Colour</option>
                            </select>
                        </label>

                        <label class="w-20">
                            <span class="rs-label">Order</span>
                            <input type="number" name="sort_order" class="rs-input num"
                                   value="<?= (int) $attribute['sort_order'] ?>">
                        </label>

                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_selectable" value="1" class="accent-mulberry"
                                       <?= (int) $attribute['is_selectable'] === 1 ? 'checked' : '' ?>>
                                <span title="The customer picks one when ordering">Customer chooses</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_filterable" value="1" class="accent-mulberry"
                                       <?= (int) $attribute['is_filterable'] === 1 ? 'checked' : '' ?>>
                                <span>Filter</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                                       <?= (int) $attribute['is_active'] === 1 ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>

                        <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Save</button>
                    </form>

                    <!-- its values -->
                    <div class="p-5">
                        <?php if ($attribute['values'] === []): ?>
                            <p class="rs-help">No values yet.</p>
                        <?php else: ?>
                            <ul class="flex flex-wrap gap-2">
                                <?php foreach ($attribute['values'] as $value): ?>
                                    <?php $used = $usage[(int) $value['id']] ?? 0; ?>
                                    <li class="flex items-center gap-2 border border-shell-line bg-shell-deep/40 px-2.5 py-1.5">
                                        <?php if (! empty($value['swatch_hex'])): ?>
                                            <span class="h-4 w-4 rounded-full border border-shell-line"
                                                  style="background: <?= esc($value['swatch_hex'], 'attr') ?>"></span>
                                        <?php endif; ?>

                                        <span class="text-sm"><?= esc($value['label']) ?></span>

                                        <?php if ($used > 0): ?>
                                            <?php /* The count is what makes deletion safe to reason
                                                     about — nobody removes a value used by 40 pieces
                                                     by accident. */ ?>
                                            <span class="rs-help num" title="On <?= $used ?> products"><?= $used ?></span>
                                        <?php else: ?>
                                            <form method="post"
                                                  action="<?= site_url('admin/attributes/values/' . $value['id'] . '/delete') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="text-ink-muted hover:text-bad"
                                                        aria-label="Remove <?= esc($value['label'], 'attr') ?>">&times;</button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" action="<?= site_url('admin/attributes/values') ?>"
                              class="mt-4 flex flex-wrap items-end gap-2 border-t border-shell-line pt-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="attribute_id" value="<?= (int) $attribute['id'] ?>">

                            <label class="min-w-40 flex-1">
                                <span class="rs-label">Add a value</span>
                                <input type="text" name="label" class="rs-input" maxlength="80"
                                       placeholder="<?= $attribute['input_type'] === 'swatch' ? 'Rose Gold' : 'e.g. 8&quot; x 8&quot;' ?>">
                            </label>

                            <?php if ($attribute['input_type'] === 'swatch'): ?>
                                <label class="w-24">
                                    <span class="rs-label">Colour</span>
                                    <input type="color" name="swatch_hex" class="rs-input h-10 p-1" value="#C0C0C0">
                                </label>
                            <?php endif; ?>

                            <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Add</button>
                        </form>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <!-- ================================ new =========================== -->
        <aside>
            <form method="post" action="<?= site_url('admin/attributes/save') ?>"
                  class="border border-shell-line bg-white p-5">
                <?= csrf_field() ?>
                <h2 class="rs-eyebrow rs-eyebrow--plain">New attribute</h2>

                <label class="mt-4 block">
                    <span class="rs-label">Name</span>
                    <input type="text" name="name" class="rs-input" maxlength="80" placeholder="Material">
                </label>

                <label class="mt-4 block">
                    <span class="rs-label">Code</span>
                    <input type="text" name="code" class="rs-input font-mono text-xs" maxlength="40"
                           placeholder="material">
                    <span class="rs-help">Letters, numbers and dashes. Used in filter links.</span>
                </label>

                <label class="mt-4 block">
                    <span class="rs-label">Shown as</span>
                    <select name="input_type" class="rs-select">
                        <option value="text">Chip</option>
                        <option value="swatch">Colour swatch</option>
                    </select>
                </label>

                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_selectable" value="1" class="accent-mulberry">
                    <span>The customer chooses one when ordering</span>
                </label>

                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_filterable" value="1" class="accent-mulberry" checked>
                    <span>Offer as a filter</span>
                </label>

                <input type="hidden" name="is_active" value="1">

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">Add attribute</button>
            </form>
        </aside>
    </div>
</div>

<?= $this->endSection() ?>
