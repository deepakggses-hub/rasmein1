<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var \App\Entities\Product $product
 * @var array<int, array<string, mixed>> $images
 * @var \App\Entities\Category|null $category
 * @var array<int, \App\Entities\Product> $related
 */
$isEnquireItem = $product->isEnquireOnly();
$gallery = $images !== [] ? $images : [['path' => null, 'alt_text' => $product->name]];
?>

<div class="rs-shell pt-8">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
</div>

<article class="rs-shell py-8 lg:py-12">
    <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">

        <!-- Gallery. Thumbnails swap the main image via anchors, so it works
             without JavaScript and each view is linkable. -->
        <div>
            <div class="relative aspect-square overflow-hidden border border-shell-line bg-shell-deep">
                <img id="product-image"
                     src="<?= esc(rs_image($gallery[0]['path'] ?? null, 'products'), 'attr') ?>"
                     alt="<?= esc($gallery[0]['alt_text'] ?? $product->name, 'attr') ?>"
                     class="h-full w-full object-cover"
                     width="800" height="800">

                <?php if ($product->hasDiscount()): ?>
                    <span class="rs-badge rs-badge--brass absolute left-4 top-4">
                        <?= $product->discountPercent() ?>% off
                    </span>
                <?php endif; ?>
            </div>

            <?php if (count($gallery) > 1): ?>
                <ul class="mt-3 grid grid-cols-5 gap-3">
                    <?php foreach ($gallery as $index => $image): ?>
                        <li>
                            <button type="button"
                                    class="block aspect-square w-full overflow-hidden border border-shell-line bg-shell-deep hover:border-brass"
                                    data-gallery-thumb
                                    data-src="<?= esc(rs_image($image['path'] ?? null, 'products'), 'attr') ?>"
                                    data-alt="<?= esc($image['alt_text'] ?? $product->name, 'attr') ?>"
                                    aria-label="View image <?= $index + 1 ?>">
                                <img src="<?= esc(rs_image($image['path'] ?? null, 'products'), 'attr') ?>"
                                     alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="lg:pt-2">
            <?php if ($category !== null): ?>
                <a href="<?= $category->url() ?>" class="rs-eyebrow">
                    <?= esc($category->name) ?>
                </a>
            <?php endif; ?>

            <h1 class="mt-4 text-3xl leading-tight sm:text-4xl"><?= esc($product->name) ?></h1>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <?php if ($product->unit_label !== null && $product->unit_label !== ''): ?>
                    <span class="rs-badge rs-badge--soft"><?= esc($product->unit_label) ?></span>
                <?php endif; ?>
                <span class="num font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">
                    SKU <?= esc($product->sku) ?>
                </span>
            </div>

            <!-- Price -->
            <p class="num mt-6 flex flex-wrap items-baseline gap-3">
                <span class="font-display text-3xl font-semibold text-mulberry"><?= esc($product->formattedPrice()) ?></span>
                <?php if ($product->hasDiscount()): ?>
                    <span class="text-lg text-ink-muted line-through"><?= esc($product->formattedCompareAtPrice()) ?></span>
                    <span class="rs-badge rs-badge--brass">Save <?= $product->discountPercent() ?>%</span>
                <?php endif; ?>
            </p>

            <!-- Stock -->
            <p class="mt-3 flex items-center gap-2 text-sm">
                <?php if ($product->inStock()): ?>
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-pista-deep" aria-hidden="true"></span>
                    <span class="text-pista-deep font-medium"><?= esc($product->stockLabel()) ?></span>
                <?php else: ?>
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-bad" aria-hidden="true"></span>
                    <span class="text-bad font-medium">Sold out — tell us and we'll let you know when it's back</span>
                <?php endif; ?>
            </p>

            <?php if ($product->short_description !== null && $product->short_description !== ''): ?>
                <p class="mt-6 leading-relaxed text-ink-soft"><?= esc($product->short_description) ?></p>
            <?php endif; ?>

            <hr class="rs-rule my-8">

            <!-- Primary action. A real form: works without JavaScript, and the
                 quantity is re-clamped server-side against live stock. -->
            <div class="space-y-3">
                <?php if (! $product->inStock()): ?>
                    <span class="rs-btn rs-btn--outline w-full" aria-disabled="true">Sold out</span>
                <?php else: ?>
                    <form method="post" action="<?= site_url('cart/add') ?>" class="flex gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                        <input type="hidden" name="return_to" value="cart">
                        <label>
                            <span class="sr-only">Quantity</span>
                            <input type="number" name="quantity" class="rs-input num w-20 text-center"
                                   value="1" min="1" max="99" inputmode="numeric">
                        </label>
                        <button type="submit" class="rs-btn rs-btn--primary flex-1">
                            <?= esc($product->ctaLabel('add')) ?>
                        </button>
                    </form>
                <?php endif; ?>

                <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--outline w-full">
                    Put this in a gift box
                </a>
            </div>

            <?php if ($isEnquireItem): ?>
                <p class="mt-6 flex gap-3 border border-pista/40 bg-pista/10 p-4 text-sm">
                    <span class="rs-badge rs-badge--enquire shrink-0">Quoted</span>
                    <span class="text-ink-soft">
                        This item is quoted rather than sold online — tell us the quantity
                        and we'll come back with a price.
                    </span>
                </p>
            <?php endif; ?>

            <!-- Detail -->
            <?php if ($product->description !== null && $product->description !== ''): ?>
                <div class="mt-10 border-t border-shell-line pt-8">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">About this</h2>
                    <div class="rs-prose mt-4 text-ink-soft">
                        <p><?= nl2br(esc($product->description)) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-shell-line pt-8 text-sm">
                <?php if ($product->weight_grams !== null && $product->weight_grams > 0): ?>
                    <div>
                        <dt class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">Weight</dt>
                        <dd class="num mt-1 font-medium"><?= (int) $product->weight_grams ?> g</dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">Box slots</dt>
                    <dd class="num mt-1 font-medium">
                        <?= (int) $product->giftbox_slots ?>
                        <?= $product->giftbox_slots === 1 ? 'compartment' : 'compartments' ?>
                    </dd>
                </div>
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">Dispatch</dt>
                    <dd class="mt-1 font-medium">Within 48 hours</dd>
                </div>
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">Delivery</dt>
                    <dd class="mt-1 font-medium">Free above <?= rs_money(1500) ?></dd>
                </div>
            </dl>
        </div>
    </div>
</article>

<?php if ($related !== []): ?>
    <section class="border-t border-shell-line bg-shell-deep py-14 lg:py-18">
        <div class="rs-shell">
            <p class="rs-eyebrow">Goes well with</p>
            <h2 class="mt-4 text-2xl sm:text-3xl">Others often sent alongside this.</h2>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($related as $item): ?>
                    <?= view('partials/product_card', ['product' => $item]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= $this->endSection() ?>
