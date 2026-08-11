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

        <?php /* Gallery. Thumbnails sit in a vertical rail beside the image on
                 wide screens and run horizontally below it on narrow ones — a
                 vertical strip on a phone steals the width the photograph needs.
                 They swap the main image with a script, but each thumbnail is a
                 real button so keyboard and screen-reader users reach every
                 view, and the first image renders with no script at all. */ ?>
        <div class="rs-gallery-wrap">
            <div class="relative aspect-[4/5] overflow-hidden bg-shell-deep">
                <img id="product-image"
                     src="<?= rs_image($gallery[0]['path'] ?? null, 'products') ?>"
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
                <ul class="rs-thumbs">
                    <?php foreach ($gallery as $index => $image): ?>
                        <li>
                            <button type="button"
                                    class="rs-thumb<?= $index === 0 ? ' is-current' : '' ?>"
                                    data-gallery-thumb
                                    data-src="<?= rs_image($image['path'] ?? null, 'products') ?>"
                                    data-alt="<?= esc($image['alt_text'] ?? $product->name, 'attr') ?>"
                                    aria-label="View image <?= $index + 1 ?>">
                                <img src="<?= rs_image($image['path'] ?? null, 'products') ?>"
                                     alt="" loading="lazy" decoding="async">
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="lg:pt-2">
            <?php
            /*
             * The design's eyebrow is two parts: the category, then a label the
             * shop types per product ("WEDDING · SIGNATURE HAMPER"). Either half
             * may be absent, so the separator only appears when both are there.
             */
            $eyebrowParts = array_values(array_filter([
                $category !== null ? $category->name : null,
                $product->eyebrow_label ?: null,
            ]));
            ?>
            <?php if ($eyebrowParts !== []): ?>
                <p class="font-mono text-[0.625rem] tracking-[0.2em] text-brass uppercase">
                    <?php if ($category !== null): ?>
                        <a href="<?= $category->url() ?>" class="hover:text-mulberry"><?= esc($category->name) ?></a>
                    <?php endif; ?>
                    <?php if (count($eyebrowParts) === 2): ?>
                        <span aria-hidden="true">&middot;</span>
                    <?php endif; ?>
                    <?php if ($product->eyebrow_label): ?>
                        <?= esc($product->eyebrow_label) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php
            /*
             * The design sets the closing clause of the title in gold italic.
             * Split on the last two words rather than asking a shop to mark
             * every product name up by hand — and fall back to the plain
             * heading when a name is too short to split sensibly.
             */
            $titleWords = preg_split('/\s+/', trim((string) $product->name)) ?: [];
            $tail = count($titleWords) >= 4 ? implode(' ', array_splice($titleWords, -2)) : '';
            $head = $tail === '' ? (string) $product->name : implode(' ', $titleWords);
            ?>
            <h1 class="rs-display rs-display--lg mt-4">
                <?= esc($head) ?><?php if ($tail !== ''): ?> <em><?= esc($tail) ?></em><?php endif; ?>
            </h1>

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

            <?php if ($product->rating_average !== null): ?>
                <?php $stars = (int) round((float) $product->rating_average); ?>
                <p class="mt-3 flex items-center gap-2.5 text-sm">
                    <span class="flex gap-0.5 text-brass" aria-hidden="true">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= $i <= $stars ? '' : 'opacity-30' ?>"><?= rs_icon('star', 'h-3.5 w-3.5') ?></span>
                        <?php endfor; ?>
                    </span>
                    <span class="num text-ink-muted">
                        <?= esc(number_format((float) $product->rating_average, 1)) ?>
                        <?php if ((int) $product->review_count > 0): ?>
                            &middot; <?= (int) $product->review_count ?>
                            review<?= (int) $product->review_count === 1 ? '' : 's' ?>
                        <?php endif; ?>
                    </span>
                </p>
            <?php endif; ?>

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
                    <?php /* A real form throughout: the stepper buttons are an
                             enhancement, and the number input still works when
                             the script does not load. Quantity is re-clamped
                             server-side against live stock regardless. */ ?>
                    <form method="post" action="<?= site_url('cart/add') ?>" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                        <input type="hidden" name="return_to" value="cart">

                        <div class="flex flex-wrap items-center gap-4">
                            <span class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Quantity</span>
                            <span class="rs-stepper" data-stepper>
                                <button type="button" data-step="-1" aria-label="One fewer">
                                    <?= rs_icon('close', 'h-3 w-3') ?>
                                </button>
                                <label>
                                    <span class="sr-only">Quantity</span>
                                    <input type="number" name="quantity" value="1" min="1" max="99" inputmode="numeric">
                                </label>
                                <button type="button" data-step="1" aria-label="One more">+</button>
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <button type="submit" class="rs-btn rs-btn--primary w-full">
                                <?= esc($product->ctaLabel('add')) ?>
                            </button>
                            <button type="submit" name="checkout" value="1" class="rs-btn rs-btn--gold w-full">
                                Buy now
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--outline w-full">
                    Put this in a gift box
                </a>
            </div>

            <?php /* Share. Plain links, not a script-injected widget: they work
                     with JavaScript off, load no third-party code, and send no
                     data to a social network before the person clicks. "Copy
                     link" is the exception and degrades to the visible URL. */ ?>
            <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-2 text-[0.625rem] tracking-[0.16em] uppercase">
                <span class="font-mono text-ink-muted">Share</span>
                <?php $shareUrl = current_url(); ?>
                <a href="https://www.pinterest.com/pin/create/button/?url=<?= urlencode($shareUrl) ?>&description=<?= urlencode((string) $product->name) ?>"
                   target="_blank" rel="noopener noreferrer" class="rs-sharelink">Pinterest</a>
                <a href="https://wa.me/?text=<?= urlencode($product->name . ' — ' . $shareUrl) ?>"
                   target="_blank" rel="noopener noreferrer" class="rs-sharelink">WhatsApp</a>
                <button type="button" class="rs-sharelink" data-copy="<?= esc($shareUrl, 'attr') ?>">
                    <span data-copy-label>Copy link</span>
                </button>
            </div>

            <?php /* The three promises from the design. Content, not chrome —
                     these answer the questions a gift buyer actually has. */ ?>
            <ul class="mt-7 grid grid-cols-3 gap-2 border border-shell-line bg-shell-deep/60 p-4 text-center">
                <?php foreach ([
                    ['giftbox', 'Complimentary gift wrap'],
                    ['store',   'Hand-crafted in India'],
                    ['orders',  'Ships within 3 days'],
                ] as [$icon, $label]): ?>
                    <li class="flex flex-col items-center gap-2">
                        <span class="text-brass"><?= rs_icon($icon, 'h-5 w-5') ?></span>
                        <span class="text-xs leading-snug text-ink-soft"><?= esc($label) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($isEnquireItem): ?>
                <p class="mt-6 flex gap-3 border border-pista/40 bg-pista/10 p-4 text-sm">
                    <span class="rs-badge rs-badge--enquire shrink-0">Quoted</span>
                    <span class="text-ink-soft">
                        This item is quoted rather than sold online — tell us the quantity
                        and we'll come back with a price.
                    </span>
                </p>
            <?php endif; ?>

            <?php
            /*
             * The panels from the design.
             *
             * Native <details>, so they work with no JavaScript and are announced
             * correctly. A panel with nothing in it is NOT rendered — an empty
             * heading a customer opens for nothing is worse than one fewer panel.
             * The first with content opens by default: a buyer should not have to
             * click to find out what a thing is.
             */
            $panels = [];

            if (trim((string) $product->composition) !== '') {
                $panels[] = ['The composition', nl2br(esc($product->composition)), true];
            }

            if (trim((string) $product->description) !== '') {
                // Already sanitised on save by HtmlSanitiser, so it is output raw.
                $panels[] = ['About this piece', $product->description, false];
            }

            $panels[] = ['Specifications', null, false];

            if (trim((string) $product->packaging_note) !== '') {
                $panels[] = ['Packaging & delivery', nl2br(esc($product->packaging_note)), false];
            }

            if (trim((string) $product->care_note) !== '') {
                $panels[] = ['Care instructions', nl2br(esc($product->care_note)), false];
            }

            if (trim((string) $product->personalisation_note) !== '') {
                $panels[] = ['Personalise this piece', nl2br(esc($product->personalisation_note)), false];
            }

            $firstOpen = true;
            ?>

            <div class="mt-10 border-t border-shell-line">
                <?php foreach ($panels as [$heading, $body, $wantsOpen]): ?>
                    <?php $open = $wantsOpen && $firstOpen; if ($open) { $firstOpen = false; } ?>
                    <details class="rs-disclose" <?= $open ? 'open' : '' ?>>
                        <summary><?= esc($heading) ?></summary>
                        <div class="rs-disclose__body <?= $body === null ? '' : 'rs-prose' ?>">
                            <?php if ($body === null): ?>
                                <?php /* Specifications are derived, not typed — SKU,
                                         weight and what it takes up in a gift box. */ ?>
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                    <dt class="text-ink-muted">Reference</dt>
                                    <dd class="num"><?= esc($product->sku) ?></dd>

                                    <?php if (! empty($product->material)): ?>
                                        <dt class="text-ink-muted">Material</dt>
                                        <dd><?= esc($product->material) ?></dd>
                                    <?php endif; ?>

                                    <?php if (! empty($product->unit_label)): ?>
                                        <dt class="text-ink-muted">Supplied as</dt>
                                        <dd><?= esc($product->unit_label) ?></dd>
                                    <?php endif; ?>

                                    <?php if (! empty($product->weight_grams)): ?>
                                        <dt class="text-ink-muted">Weight</dt>
                                        <dd class="num"><?= (int) $product->weight_grams ?> g</dd>
                                    <?php endif; ?>

                                    <?php if ($product->is_giftbox_eligible): ?>
                                        <dt class="text-ink-muted">In a gift box</dt>
                                        <dd class="num">
                                            <?= (int) $product->giftbox_slots ?>
                                            slot<?= (int) $product->giftbox_slots === 1 ? '' : 's' ?>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                            <?php else: ?>
                                <?= $body ?>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</article>

<?php if ($related !== []): ?>
    <section class="border-t border-shell-line bg-shell-deep py-14 lg:py-18">
        <div class="rs-shell">
            <p class="rs-eyebrow">Goes well with</p>
            <h2 class="mt-4 text-2xl sm:text-3xl">Others often sent alongside this.</h2>

            <div class="mt-10 rs-grid">
                <?php foreach ($related as $item): ?>
                    <?= view('partials/product_card', ['product' => $item]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= $this->endSection() ?>
