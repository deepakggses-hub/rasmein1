<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Categories as a tree.
 *
 * The list is ordered by path, which IS depth-first order — a child's path is
 * its parent's path plus a separator — so indenting by depth renders the
 * hierarchy without any recursion in the view.
 *
 * @var array<int, \App\Entities\Category> $categories
 * @var array<int, int> $counts
 */
$editing = $editing ?? null;
$action  = $editing === null ? site_url('admin/categories') : site_url('admin/categories/' . $editing->id);
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');

// A category cannot be moved inside itself or its own descendants, so those
// options are removed from the dropdown rather than offered and then refused.
$forbidden = [];

if ($editing !== null) {
    $ownPath = (string) ($editing->path ?? '');

    foreach ($categories as $candidate) {
        $candidatePath = (string) ($candidate->path ?? '');

        if ($candidatePath === $ownPath || ($ownPath !== '' && str_starts_with($candidatePath, $ownPath . '/'))) {
            $forbidden[] = (int) $candidate->id;
        }
    }
}
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Categories',
    'subheading' => 'Each category is a page at its own web address. Nest one inside another and the address follows.',
    'actions'    => '<a href="' . site_url('admin/products') . '" class="rs-btn rs-btn--outline rs-btn--sm">Products</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_23rem] lg:items-start lg:px-8">

    <!-- =============================== tree =============================== -->
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($categories === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No categories yet. Add the first one alongside.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Category</th>
                        <th class="px-4 py-2.5">Web address</th>
                        <th class="num px-4 py-2.5 text-right">Products</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $depth = (int) ($category->depth ?? 0);
                        $isEditing = $editing !== null && (int) $editing->id === (int) $category->id;
                        ?>
                        <tr class="hover:bg-shell <?= $isEditing ? 'bg-shell-deep' : '' ?>">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2" style="padding-left: <?= $depth * 1.25 ?>rem">
                                    <?php if ($depth > 0): ?>
                                        <span class="font-mono text-ink-muted" aria-hidden="true">└</span>
                                    <?php endif; ?>
                                    <span class="block h-8 w-8 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= rs_url($category->imageUrl()) ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <span class="font-medium"><?= esc($category->name) ?></span>
                                    <?php if ($category->is_featured): ?>
                                        <span class="rs-badge rs-badge--brass">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="<?= rs_url((string) ($category->path ?? $category->slug)) ?>"
                                   target="_blank" rel="noopener"
                                   class="rs-link font-mono text-xs text-ink-muted">
                                    /<?= esc((string) ($category->path ?? $category->slug)) ?>
                                </a>
                            </td>
                            <td class="num px-4 py-2.5 text-right"><?= (int) ($counts[(int) $category->id] ?? 0) ?></td>
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

    <!-- =============================== form =============================== -->
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
                    <span class="rs-label">Sits inside</span>
                    <select name="parent_id" class="rs-select">
                        <option value="">Top level — its own address</option>
                        <?php foreach ($categories as $candidate): ?>
                            <?php
                            $cid = (int) $candidate->id;
                            $cdepth = (int) ($candidate->depth ?? 0);

                            // Already at the deepest permitted level: nothing can
                            // go inside it.
                            if ($cdepth >= $maxDepth) {
                                continue;
                            }

                            if (in_array($cid, $forbidden, true)) {
                                continue;
                            }
                            ?>
                            <option value="<?= $cid ?>"
                                <?= (int) (old('parent_id') ?? $editing->parent_id ?? 0) === $cid ? 'selected' : '' ?>>
                                <?= str_repeat('— ', $cdepth) ?><?= esc($candidate->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="rs-help">
                        Choosing a parent puts this category at
                        <span class="font-mono">/parent/this-one</span>.
                        <?php if ($editing !== null): ?>
                            Moving it moves every subcategory with it.
                        <?php endif; ?>
                    </span>
                </label>

                <label class="mt-4 block">
                    <span class="rs-label">URL slug</span>
                    <input type="text" name="slug" class="rs-input font-mono text-xs" maxlength="160"
                           value="<?= $v('slug', $editing->slug ?? '') ?>">
                    <span class="rs-help">
                        Just this level, not the whole path. Blank builds it from the name.
                        <?php if ($editing !== null && ! empty($editing->path)): ?>
                            <br>Currently at <span class="font-mono">/<?= esc((string) $editing->path) ?></span>.
                        <?php endif; ?>
                    </span>
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

                <details class="mt-4">
                    <summary class="cursor-pointer font-mono text-[0.625rem] tracking-widest text-brass uppercase">
                        Search listing
                    </summary>
                    <label class="mt-3 block">
                        <span class="rs-label">Meta title</span>
                        <input type="text" name="meta_title" class="rs-input" maxlength="191"
                               value="<?= $v('meta_title', $editing->meta_title ?? '') ?>">
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">Meta description</span>
                        <input type="text" name="meta_description" class="rs-input" maxlength="255"
                               value="<?= $v('meta_description', $editing->meta_description ?? '') ?>">
                    </label>
                </details>

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                    <?= $editing === null ? 'Add category' : 'Save changes' ?>
                </button>

                <?php if ($editing !== null): ?>
                    <a href="<?= site_url('admin/categories') ?>" class="rs-btn rs-btn--outline rs-btn--sm mt-2 w-full">Cancel</a>
                <?php endif; ?>
            </form>

            <?php if ($editing !== null): ?>
                <form method="post" action="<?= site_url('admin/categories/' . $editing->id . '/delete') ?>" class="mt-3"
                      data-confirm="Remove &ldquo;<?= esc($editing->name, 'attr') ?>&rdquo;?"
                      data-confirm-detail="Its products stay, uncategorised. A category with subcategories cannot be removed."
                      data-confirm-action="Remove it">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this category</button>
                </form>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
