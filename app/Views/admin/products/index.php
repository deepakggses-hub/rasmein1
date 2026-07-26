<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Products',
    'subheading' => $total . ' product' . ($total === 1 ? '' : 's') . ' matching.',
    'actions'    => $canManage
        ? '<a href="' . site_url('admin/products/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New product</a>'
          . '<a href="' . site_url('admin/categories') . '" class="rs-btn rs-btn--outline rs-btn--sm">Categories</a>'
        : '',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label class="min-w-48 flex-1">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input" placeholder="Name or SKU"
                   value="<?= esc($filters['q'] ?? '', 'attr') ?>">
        </label>
        <label>
            <span class="rs-label">Category</span>
            <select name="category" class="rs-select w-auto">
                <option value="">Any</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category->id ?>" <?= (int) ($filters['category'] ?? 0) === (int) $category->id ? 'selected' : '' ?>>
                        <?= esc($category->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="rs-label">Show</span>
            <select name="state" class="rs-select w-auto">
                <?php foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Hidden', 'low' => 'Low stock'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filters['state'] ?? '') === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Filter</button>
        <a href="<?= site_url('admin/products') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($products === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No products match that.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Product</th>
                        <th class="px-4 py-2.5">SKU</th>
                        <th class="px-4 py-2.5">Category</th>
                        <th class="num px-4 py-2.5 text-right">Price</th>
                        <th class="num px-4 py-2.5 text-right">Stock</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="block h-10 w-8 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= esc($product->imageUrl(), 'attr') ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <span class="min-w-0">
                                        <?php if ($canManage): ?>
                                            <a href="<?= site_url('admin/products/' . $product->id . '/edit') ?>" class="rs-link font-medium">
                                                <?= esc(rs_excerpt($product->name, 32)) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= esc(rs_excerpt($product->name, 32)) ?>
                                        <?php endif; ?>
                                        <?php if ($product->sale_mode !== 'inherit'): ?>
                                            <span class="rs-badge rs-badge--enquire ml-1"><?= esc($product->sale_mode === 'enquire_now' ? 'Quoted' : 'Buy') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="num px-4 py-2.5 font-mono text-xs text-ink-muted"><?= esc($product->sku) ?></td>
                            <td class="px-4 py-2.5 text-ink-muted"><?= esc($product->category_name ?? '—') ?></td>
                            <td class="num px-4 py-2.5 text-right font-medium"><?= esc($product->formattedPrice()) ?></td>
                            <td class="num px-4 py-2.5 text-right <?= ! $product->inStock() ? 'font-semibold text-bad' : ($product->isLowStock() ? 'font-semibold text-warn' : '') ?>">
                                <?= $product->track_inventory ? (int) $product->stock_qty : '∞' ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge <?= $product->is_active ? 'rs-badge--soft' : 'rs-badge--out' ?>">
                                    <?= $product->is_active ? 'Live' : 'Hidden' ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <?php if ($canManage): ?>
                                    <a href="<?= site_url('admin/products/' . $product->id . '/edit') ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= view('admin/partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
