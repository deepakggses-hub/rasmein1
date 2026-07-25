<?php
/**
 * Filter sidebar. A plain GET form — it works with JavaScript disabled, and
 * every control is a real input whose name matches what the controller reads.
 *
 * @var array<string, mixed> $filters
 * @var array<int, \App\Entities\Category> $categories
 * @var array{min: float, max: float} $priceRange
 * @var array<string, mixed> $context
 */
$lockedCategory   = $context['lockedCategory']   ?? null;
$lockedCollection = $context['lockedCollection'] ?? null;
$term             = $filters['q'] ?? null;
?>
<form method="get" class="space-y-8" aria-label="Filter products">
    <?php /* Preserve the search term and sort across filter submits. */ ?>
    <?php if ($term !== null && $term !== ''): ?>
        <input type="hidden" name="q" value="<?= esc($term, 'attr') ?>">
    <?php endif; ?>
    <?php if (($sort ?? 'featured') !== 'featured'): ?>
        <input type="hidden" name="sort" value="<?= esc($sort, 'attr') ?>">
    <?php endif; ?>

    <?php if ($lockedCategory === null): ?>
        <fieldset>
            <legend class="rs-eyebrow rs-eyebrow--plain">Category</legend>
            <ul class="mt-4 space-y-1.5">
                <li>
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                        <input type="radio" name="category" value=""
                               class="accent-mulberry"
                               <?= empty($filters['category']) ? 'checked' : '' ?>>
                        <span>All categories</span>
                    </label>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                            <input type="radio" name="category" value="<?= (int) $category->id ?>"
                                   class="accent-mulberry"
                                   <?= (int) ($filters['category'] ?? 0) === (int) $category->id ? 'checked' : '' ?>>
                            <span class="flex-1"><?= esc($category->name) ?></span>
                            <?php if ($category->productCount() !== null): ?>
                                <span class="num font-mono text-[0.625rem] text-ink-muted"><?= (int) $category->productCount() ?></span>
                            <?php endif; ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    <?php endif; ?>

    <fieldset>
        <legend class="rs-eyebrow rs-eyebrow--plain">Price</legend>
        <div class="mt-4 flex items-center gap-2">
            <label class="flex-1">
                <span class="sr-only">Minimum price</span>
                <input type="number" name="min_price" class="rs-input num" inputmode="numeric"
                       min="0" step="10"
                       placeholder="<?= (int) $priceRange['min'] ?>"
                       value="<?= $filters['min_price'] !== null ? (int) $filters['min_price'] : '' ?>">
            </label>
            <span class="text-ink-muted" aria-hidden="true">&ndash;</span>
            <label class="flex-1">
                <span class="sr-only">Maximum price</span>
                <input type="number" name="max_price" class="rs-input num" inputmode="numeric"
                       min="0" step="10"
                       placeholder="<?= (int) ceil($priceRange['max']) ?>"
                       value="<?= $filters['max_price'] !== null ? (int) $filters['max_price'] : '' ?>">
            </label>
        </div>
        <p class="rs-help">Everything in stock sits between <?= rs_money($priceRange['min']) ?> and <?= rs_money($priceRange['max']) ?>.</p>
    </fieldset>

    <fieldset>
        <legend class="rs-eyebrow rs-eyebrow--plain">Show only</legend>
        <ul class="mt-4 space-y-2">
            <li>
                <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                    <input type="checkbox" name="in_stock" value="1" class="accent-mulberry"
                           <?= ! empty($filters['in_stock']) ? 'checked' : '' ?>>
                    <span>In stock</span>
                </label>
            </li>
            <li>
                <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                    <input type="checkbox" name="giftable" value="1" class="accent-mulberry"
                           <?= ! empty($filters['giftable']) ? 'checked' : '' ?>>
                    <span>Can go in a gift box</span>
                </label>
            </li>
        </ul>
    </fieldset>

    <div class="flex flex-wrap gap-2 border-t border-shell-line pt-6">
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Apply filters</button>
        <?php
        $hasFilters = ! empty($filters['category']) && $lockedCategory === null
            || $filters['min_price'] !== null
            || $filters['max_price'] !== null
            || ! empty($filters['in_stock'])
            || ! empty($filters['giftable']);
        ?>
        <?php if ($hasFilters): ?>
            <?php
            // "Clear" keeps the page context (category, collection, search)
            // and drops only the sidebar choices.
            $clearUrl = current_url();
            if ($term !== null && $term !== '') {
                $clearUrl .= '?q=' . rawurlencode($term);
            }
            ?>
            <a href="<?= esc($clearUrl, 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
        <?php endif; ?>
    </div>
</form>
