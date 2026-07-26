<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/** @var \App\Entities\Category|null $editing */
$action = $editing === null
    ? site_url('admin/categories')
    : site_url('admin/categories/' . $editing->id);
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Categories',
    'subheading' => 'Deleting one leaves its products in place, just uncategorised.',
    'actions'    => '<a href="' . site_url('admin/products') . '" class="rs-btn rs-btn--outline rs-btn--sm">Products</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_22rem] lg:items-start lg:px-8">

    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($categories === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No categories yet. Add the first one alongside.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Name</th>
                        <th class="px-4 py-2.5">Slug</th>
                        <th class="num px-4 py-2.5 text-right">Products</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($categories as $category): ?>
                        <tr class="hover:bg-shell <?= $editing !== null && (int) $editing->id === (int) $category->id ? 'bg-shell-deep' : '' ?>">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="block h-9 w-9 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= esc($category->imageUrl(), 'attr') ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <span class="font-medium"><?= esc($category->name) ?></span>
                                    <?php if ($category->is_featured): ?>
                                        <span class="rs-badge rs-badge--brass">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-muted"><?= esc($category->slug) ?></td>
                            <td class="num px-4 py-2.5 text-right"><?= (int) ($category->productCount() ?? 0) ?></td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge <?= $category->is_active ? 'rs-badge--soft' : 'rs-badge--out' ?>">
                                    <?= $category->is_active ? 'Live' : 'Hidden' ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <?php if ($canManage): ?>
                                    <a href="<?= site_url('admin/categories/' . $category->id . '/edit') ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <aside>
            <form method="post" action="<?= $action ?>" enctype="multipart/form-data"
                  class="border border-shell-line bg-white p-5">
                <?= csrf_field() ?>
                <h2 class="rs-eyebrow rs-eyebrow--plain">
                    <?= $editing === null ? 'Add a category' : 'Edit ' . esc($editing->name) ?>
                </h2>

                <label class="mt-4 block">
                    <span class="rs-label">Name <span class="text-bad">*</span></span>
                    <input type="text" name="name" class="rs-input" required maxlength="120"
                           value="<?= $v('name', $editing->name ?? '') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">URL slug</span>
                    <input type="text" name="slug" class="rs-input" maxlength="160"
                           value="<?= $v('slug', $editing->slug ?? '') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Description</span>
                    <textarea name="description" class="rs-textarea" rows="3"><?= esc(old('description') ?? $editing->description ?? '') ?></textarea>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Image</span>
                    <input type="file" name="image" class="rs-input" accept="image/jpeg,image/png,image/webp">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($editing->sort_order ?? 0)) ?>">
                </label>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($editing === null || $editing->is_active) ? 'checked' : '' ?>>
                    <span>Live on the storefront</span>
                </label>
                <label class="mt-3 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_featured" value="1" class="accent-mulberry"
                           <?= ($editing !== null && $editing->is_featured) ? 'checked' : '' ?>>
                    <span>Featured on the homepage</span>
                </label>

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                    <?= $editing === null ? 'Add category' : 'Save changes' ?>
                </button>

                <?php if ($editing !== null): ?>
                    <a href="<?= site_url('admin/categories') ?>" class="rs-btn rs-btn--outline rs-btn--sm mt-2 w-full">Cancel</a>
                <?php endif; ?>
            </form>

            <?php if ($editing !== null): ?>
                <form method="post" action="<?= site_url('admin/categories/' . $editing->id . '/delete') ?>" class="mt-3"
                      onsubmit="return confirm('Remove this category? Its products stay, uncategorised.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this category</button>
                </form>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
