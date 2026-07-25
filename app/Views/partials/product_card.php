<?php
/**
 * @var \App\Entities\Product $product
 */
?>
<article class="rs-card group flex flex-col overflow-hidden">
    <a href="<?= esc($product->url(), 'attr') ?>" class="relative block aspect-[4/5] overflow-hidden bg-shell-deep">
        <img src="<?= esc($product->imageUrl(), 'attr') ?>"
             alt="<?= esc($product->name, 'attr') ?>"
             loading="lazy"
             decoding="async"
             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]">

        <?php if ($product->hasDiscount()): ?>
            <span class="rs-badge rs-badge--brass absolute left-3 top-3">
                <?= $product->discountPercent() ?>% off
            </span>
        <?php endif; ?>

        <?php if (! $product->inStock()): ?>
            <span class="rs-badge rs-badge--out absolute right-3 top-3">Sold out</span>
        <?php elseif ($product->isLowStock()): ?>
            <span class="rs-badge rs-badge--soft absolute right-3 top-3"><?= esc($product->stockLabel()) ?></span>
        <?php endif; ?>
    </a>

    <div class="flex flex-1 flex-col p-4">
        <?php if ($product->unit_label !== null && $product->unit_label !== ''): ?>
            <p class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">
                <?= esc($product->unit_label) ?>
            </p>
        <?php endif; ?>

        <h3 class="mt-1 text-base leading-snug font-semibold">
            <a href="<?= esc($product->url(), 'attr') ?>" class="rs-link"><?= esc($product->name) ?></a>
        </h3>

        <?php if ($product->short_description !== null && $product->short_description !== ''): ?>
            <p class="mt-1.5 text-sm text-ink-muted"><?= esc(rs_excerpt($product->short_description, 72)) ?></p>
        <?php endif; ?>

        <div class="mt-auto flex items-end justify-between gap-3 pt-4">
            <p class="num">
                <span class="text-lg font-bold text-mulberry"><?= esc($product->formattedPrice()) ?></span>
                <?php if ($product->hasDiscount()): ?>
                    <span class="ml-1.5 text-sm text-ink-muted line-through">
                        <?= esc($product->formattedCompareAtPrice()) ?>
                    </span>
                <?php endif; ?>
            </p>

            <?php if ($product->inStock()): ?>
                <a href="<?= esc($product->url(), 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm">
                    <?= esc($product->ctaLabel('short')) ?>
                </a>
            <?php else: ?>
                <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Sold out</span>
            <?php endif; ?>
        </div>
    </div>
</article>
