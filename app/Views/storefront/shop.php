<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * One template for all four listing contexts: everything, a category,
 * a collection, and a search.
 *
 * @var array<string, mixed> $context
 * @var array<int, \App\Entities\Product> $products
 * @var \CodeIgniter\Pager\Pager $pager
 * @var int $total
 * @var array<string, mixed> $filters
 * @var string $sort
 * @var array<string, string> $sortOptions
 */
$term = $filters['q'] ?? null;
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10 lg:py-14">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>

        <p class="rs-eyebrow mt-6"><?= esc($context['eyebrow'] ?? 'The shop') ?></p>
        <h1 class="mt-4 max-w-2xl text-4xl sm:text-[2.75rem]"><?= esc($context['heading']) ?></h1>

        <?php if (! empty($context['intro'])): ?>
            <p class="mt-4 max-w-xl leading-relaxed text-ink-muted"><?= esc($context['intro']) ?></p>
        <?php endif; ?>

        <!-- Search sits in the header of every listing page, pre-filled. -->
        <form method="get" action="<?= site_url('search') ?>" class="mt-8 flex max-w-md gap-2" role="search">
            <label class="flex-1">
                <span class="sr-only">Search products</span>
                <input type="search" name="q" class="rs-input" placeholder="Search for chocolate, tea, candles…"
                       value="<?= esc($term ?? '', 'attr') ?>">
            </label>
            <button type="submit" class="rs-btn rs-btn--primary">Search</button>
        </form>
    </div>
</header>

<div class="rs-shell py-10 lg:py-14">
    <div class="lg:grid lg:grid-cols-[16rem_1fr] lg:gap-12">

        <!-- Filters. Collapsed into a disclosure on small screens. -->
        <aside class="lg:sticky lg:top-32 lg:self-start">
            <details class="lg:hidden" <?= $total === 0 ? 'open' : '' ?>>
                <summary class="rs-btn rs-btn--outline w-full cursor-pointer justify-between">
                    Filters
                    <span class="num font-mono text-[0.625rem]"><?= (int) $total ?> items</span>
                </summary>
                <div class="mt-6 border-t border-shell-line pt-6">
                    <?= view('partials/filters', [
                        'filters' => $filters, 'categories' => $categories,
                        'priceRange' => $priceRange, 'context' => $context, 'sort' => $sort,
                    ]) ?>
                </div>
            </details>

            <div class="hidden lg:block">
                <?= view('partials/filters', [
                    'filters' => $filters, 'categories' => $categories,
                    'priceRange' => $priceRange, 'context' => $context, 'sort' => $sort,
                ]) ?>
            </div>
        </aside>

        <section class="mt-8 lg:mt-0">
            <!-- Result count + sort -->
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-shell-line pb-4">
                <p class="num text-sm text-ink-muted">
                    <?php if ($total === 0): ?>
                        No matches
                    <?php else: ?>
                        <span class="font-semibold text-ink"><?= (int) $total ?></span>
                        <?= $total === 1 ? 'product' : 'products' ?>
                    <?php endif; ?>
                </p>

                <form method="get" class="flex items-center gap-2">
                    <?php /* Keep every active filter when sort changes. */ ?>
                    <?php foreach (['q', 'category', 'min_price', 'max_price', 'in_stock', 'giftable'] as $key): ?>
                        <?php $value = $filters[$key] ?? null; ?>
                        <?php if ($key === 'category' && ($context['lockedCategory'] ?? null) !== null) { continue; } ?>
                        <?php if ($value !== null && $value !== '' && $value !== false): ?>
                            <input type="hidden" name="<?= esc($key, 'attr') ?>"
                                   value="<?= esc(is_bool($value) ? '1' : (string) $value, 'attr') ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <label for="sort" class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">Sort</label>
                    <select id="sort" name="sort" class="rs-select w-auto py-2 text-sm" data-auto-submit>
                        <?php foreach ($sortOptions as $key => $label): ?>
                            <option value="<?= esc($key, 'attr') ?>" <?= $sort === $key ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Go</button></noscript>
                </form>
            </div>

            <?php if ($products === []): ?>
                <!-- Empty state: an invitation, not an apology. -->
                <div class="py-20 text-center">
                    <div class="mx-auto max-w-52">
                        <?= view('partials/tray', ['capacity' => 6, 'filled' => [], 'columns' => 3]) ?>
                    </div>
                    <h2 class="mt-10 text-2xl">Nothing matches that yet.</h2>
                    <p class="mx-auto mt-3 max-w-sm text-ink-muted">
                        <?php if ($term !== null && $term !== ''): ?>
                            No product mentions &ldquo;<?= esc($term) ?>&rdquo;. Try a shorter word, or browse a category.
                        <?php else: ?>
                            Widen the price range or clear a filter to see more.
                        <?php endif; ?>
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--primary">Browse everything</a>
                        <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--outline">Build a gift box</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($products as $product): ?>
                        <?= view('partials/product_card', ['product' => $product]) ?>
                    <?php endforeach; ?>
                </div>

                <?= view('partials/pagination', ['pager' => $pager]) ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
