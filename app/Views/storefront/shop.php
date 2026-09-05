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

<div class="rs-shell pt-6">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
</div>


<div class="rs-shell py-10 lg:py-14">
    <div class="lg:grid lg:grid-cols-[16rem_1fr] lg:gap-12">

        <!-- Filters. Collapsed into a disclosure on small screens. -->
        <aside class="rs-filtercol lg:sticky lg:top-28 lg:self-start" data-sidebar>
            <div class="mb-4 flex items-center justify-between lg:hidden">
                <span class="rs-facet__head">Filter</span>
                <button type="button" class="rs-iconbtn text-ink" data-sidebar-close aria-label="Close filters">
                    <?= rs_icon('close', 'h-5 w-5') ?>
                </button>
            </div>

            <?= view('partials/filters', ['facets' => $facets, 'active' => $active]) ?>
        </aside>

        <section class="mt-8 lg:mt-0">
            <?php /* The title sits over the products rather than in a band
                     above both columns, so the filter list starts level with
                     it — matching the design and saving a screen of scroll
                     before the first product. */ ?>
            <?php /* Title and controls share a row, as in the design: the
                     controls sit against the right edge of the grid rather
                     than on a band of their own. They wrap underneath on a
                     narrow screen. */ ?>
            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-5 border-b border-shell-line pb-5">
                <header>
                    <h1 class="rs-display rs-display--lg"><?= esc($context['heading']) ?></h1>
                    <p class="mt-3 font-mono text-[0.625rem] tracking-[0.18em] text-ink-muted uppercase" data-result-count>
                        <span class="num"><?= (int) $total ?></span>
                        <?= $total === 1 ? 'piece' : 'pieces' ?>
                        <?php if (! empty($context['intro'])): ?>
                            <span class="mx-2 text-brass" aria-hidden="true">&middot;</span>
                            <?= esc(rs_excerpt($context['intro'], 52)) ?>
                        <?php endif; ?>
                    </p>
                </header>

                <div class="flex flex-wrap items-center gap-3">
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

                    <label for="sort" class="sr-only">Sort</label>
                    <span class="rs-sortwrap">
                    <select id="sort" name="sort" class="rs-sortpill" data-auto-submit>
                        <?php /* A labelled placeholder, so the control reads as
                                 "Sort By" until a choice is made rather than
                                 looking like Featured was chosen. Disabled, so
                                 it can never be submitted. */ ?>
                        <option value="" disabled <?= ($active['sort'] ?? '') === '' ? 'selected' : '' ?>>Sort By</option>
                        <?php foreach ($sortOptions as $key => $label): ?>
                            <option value="<?= esc($key, 'attr') ?>" <?= $sort === $key ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        <?= rs_icon('sort', 'rs-sortwrap__icon') ?>
                    </span>
                    <noscript><button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Go</button></noscript>
                </form>

                <?php /* Progressive: without JavaScript the grid keeps its
                         default density and these simply are not shown. */ ?>
                <button type="button" class="rs-btn rs-btn--outline rs-btn--sm rs-filterbar" data-sidebar-open>
                    <?= rs_icon('rows', 'h-4 w-4') ?> Filter
                    <?php if ($chips !== []): ?>
                        <span class="num rs-badge rs-badge--brass"><?= count($chips) ?></span>
                    <?php endif; ?>
                </button>

                <div class="hidden items-center gap-1 sm:flex" role="group" aria-label="Grid density" data-density-group>
                    <?php foreach ([
                        'comfortable' => ['grid', 'Comfortable'],
                        'dense'       => ['rows', 'Compact'],
                        'list'        => ['rows', 'One per row'],
                    ] as $mode => [$icon, $label]): ?>
                        <button type="button" class="rs-density" data-density="<?= $mode ?>"
                                aria-pressed="false" title="<?= esc($label, 'attr') ?>">
                            <?= rs_icon($icon, 'h-4 w-4') ?>
                            <span class="sr-only"><?= esc($label) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                </div><?php /* controls */ ?>
            </div><?php /* title + controls row */ ?>

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
                <?php if ($chips !== []): ?>
                    <?php /* Removing one at a time beats a single "clear all":
                             a person who ticked four things and got two results
                             needs to see which one to loosen. */ ?>
                    <ul class="mt-5 flex flex-wrap items-center gap-2" data-chips>
                        <?php foreach ($chips as $chip): ?>
                            <li>
                                <?php /* NOT esc(..., 'attr'): that encodes / : ? and = into entities and
                                     the link 404s (CLAUDE.md §15.9, fifth occurrence).
                                     The URL is assembled by the controller from
                                     current_url() and http_build_query, which has already
                                     encoded every value. */ ?>
                                <a href="<?= $chip['url'] ?>" class="rs-chip">
                                    <?= esc($chip['label']) ?>
                                    <span aria-hidden="true">&times;</span>
                                    <span class="sr-only">Remove this filter</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li>
                            <a href="<?= site_url(uri_string()) ?>" class="rs-link text-xs text-ink-muted">Clear all</a>
                        </li>
                    </ul>
                <?php endif; ?>

                <div class="rs-grid mt-8" data-grid>
                    <?php foreach ($products as $product): ?>
                        <div class="rs-reveal">
                            <?= view('partials/product_card', [
                                'product' => $product,
                                // Batched in the controller — see
                                // ProductModel::imagesFor(). Asking per card
                                // would be one query per product on the page.
                                'images'  => $imageMap[(int) $product->id] ?? [],
                                'saved'   => in_array((int) $product->id, $savedIds ?? [], true),
                                'inBasket' => (int) ($basketQty[(int) $product->id] ?? 0),
                                'attrs'    => $attrMap[(int) $product->id] ?? [],
                            ]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?= view('partials/pagination', ['pager' => $pager]) ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
