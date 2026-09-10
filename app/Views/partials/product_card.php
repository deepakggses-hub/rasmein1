<?php
/**
 * A product card.
 *
 * Declares a container query context, so it sizes from the space it is GIVEN
 * rather than the viewport — the same component then works in a wide grid, a
 * narrow rail and a two-up mobile layout without knowing which it is in.
 *
 * Several photographs cycle on hover, with dots beneath. Every image is in the
 * markup: the alternative — fetching on hover — shows a blank frame for the
 * first few hundred milliseconds, which reads as broken. They are lazy, so the
 * cost is only paid for cards the person actually scrolls to.
 *
 * @var \App\Entities\Product $product
 * @var array<int, array<string, mixed>>|null $images  Pre-loaded, to avoid an N+1
 * @var bool $showQuick
 */
$showQuick = $showQuick ?? true;
$inStock   = $product->inStock();
$isEnquire = rs_is_enquire_mode($product->sale_mode ?? 'inherit');
$url       = site_url('product/' . $product->slug);

// Fall back to the single primary image when a caller has not batched them.
$shots = $images ?? [];

if ($shots === []) {
    $shots = [['path' => null, 'alt_text' => $product->name]];
}

$shots  = array_slice($shots, 0, 5);
$hasMany = count($shots) > 1;

$flag = null;

if (! $inStock) {
    $flag = ['Sold out', 'rs-product__flag--plain'];
} elseif (! empty($product->is_featured)) {
    $flag = ['Best seller', ''];
} elseif (! empty($product->created_at) && strtotime((string) $product->created_at) > strtotime('-21 days')) {
    $flag = ['New', 'rs-product__flag--new'];
}
?>
<article class="rs-product group" <?= $hasMany ? 'data-shots' : '' ?>>
    <div class="rs-product__frame">
        <a href="<?= $url ?>" class="absolute inset-0 z-10" tabindex="-1" aria-hidden="true"></a>

        <?php foreach ($shots as $index => $shot): ?>
            <?php
            /*
             * `sizes` describes the SLOT, not the file. The grid fits as many
             * columns as --rs-card-min allows, so a card is roughly a quarter of
             * the viewport on a wide screen and half on a phone. Getting this
             * wrong is the usual cause of a "high-res photo that still looks
             * soft": the browser picks by slot width, and a wrong hint makes it
             * pick too small.
             */
            ?>
            <?= rs_picture($shot['path'] ?? null, '(min-width: 1280px) 24vw, (min-width: 768px) 33vw, 50vw', [
                'alt'      => $index === 0 ? '' : ($shot['alt_text'] ?? ''),
                'class'    => 'rs-product__img' . ($index === 0 ? ' is-current' : ''),
                'data-shot' => (string) $index,
                'width'    => '600',
                'height'   => '732',
            ]) ?>
        <?php endforeach; ?>

        <?php if ($flag !== null): ?>
            <span class="rs-product__flag <?= $flag[1] ?>"><?= esc($flag[0]) ?></span>
        <?php endif; ?>

        <?php if (! $inStock): ?>
            <span class="absolute inset-0 z-20 grid place-items-center bg-shell/55">
                <span class="rs-badge rs-badge--out">Sold out</span>
            </span>
        <?php endif; ?>

        <?php if ($hasMany): ?>
            <?php /* Dots are buttons, not decoration: a touch device has no
                     hover, so this is the only way to reach the other
                     photographs without opening the product. */ ?>
            <div class="rs-product__dots" data-shot-dots>
                <?php foreach ($shots as $index => $shot): ?>
                    <button type="button" class="rs-product__dot <?= $index === 0 ? 'is-current' : '' ?>"
                            data-shot-dot="<?= $index ?>"
                            aria-label="Photograph <?= $index + 1 ?> of <?= count($shots) ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($showQuick && $inStock): ?>
            <div class="rs-product__quick">
                <?php /* One form, two faces: a plain Add button until there is
                         something in the basket, then a stepper. The script
                         swaps between them; with no JavaScript the Add button
                         posts normally and the stepper never appears. */ ?>
                <?php if ($isEnquire): ?>
                    <?php
                    /*
                     * In corporate mode there is no basket for this piece.
                     *
                     * A business ordering two hundred is not adding to a cart
                     * and paying — they are asking for a quote. Showing "Add to
                     * cart" beside a bulk enquiry is offering a route that ends
                     * in the wrong place.
                     */
                    ?>
                    <button type="button" class="rs-btn rs-btn--primary rs-btn--sm w-full flex-1"
                            data-bulk-enquiry
                            data-product-id="<?= (int) $product->id ?>"
                            data-product-name="<?= esc($product->name, 'attr') ?>">
                        <?= rs_icon('briefcase', 'h-4 w-4') ?>
                        Bulk enquiry
                    </button>
                <?php else: ?>
                <form method="post" action="<?= site_url('cart/add') ?>" class="flex-1"
                      data-cart data-qty="<?= (int) ($inBasket ?? 0) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="return_to" value="<?= esc(uri_string(), 'attr') ?>">

                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm w-full"
                            data-cart-add <?= ($inBasket ?? 0) > 0 ? 'hidden' : '' ?>>
                        <?= esc(rs_cta_label($product->sale_mode ?? 'inherit', 'add')) ?>
                    </button>

                    <div class="rs-qty" data-cart-step <?= ($inBasket ?? 0) > 0 ? '' : 'hidden' ?>>
                        <button type="button" class="rs-qty__btn" data-qty-down
                                aria-label="One fewer <?= esc($product->name, 'attr') ?>">&minus;</button>
                        <span class="rs-qty__n num" data-qty-value aria-live="polite"><?= (int) ($inBasket ?? 0) ?></span>
                        <button type="button" class="rs-qty__btn" data-qty-up
                                aria-label="One more <?= esc($product->name, 'attr') ?>">+</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php /* A real form, so it works with no JavaScript. The
                         script upgrades it to a background request and fills
                         the heart in place. */ ?>
                    <form method="post" action="<?= site_url('wishlist/toggle') ?>" data-wish>
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                        <input type="hidden" name="return_to" value="<?= esc(uri_string(), 'attr') ?>">
                        <button type="submit" class="rs-heart"
                                aria-pressed="<?= ! empty($saved) ? 'true' : 'false' ?>"
                                aria-label="<?= ! empty($saved) ? 'Remove' : 'Save' ?> <?= esc($product->name, 'attr') ?>">
                            <?= rs_icon('heart', 'h-4 w-4') ?>
                        </button>
                    </form>
                
            </div>
        <?php endif; ?>
    </div>

    <?php if (! empty($product->category_name)): ?>
        <p class="rs-product__eyebrow"><?= esc($product->category_name) ?></p>
    <?php endif; ?>

            <?php
            /*
             * A hint of the colours, no labels — a card has no room for them and
             * the swatch alone answers "does this come in gold?". Capped, because
             * fifteen dots is a smear rather than information.
             */
            $swatches = array_values(array_filter(
                $attrs ?? [],
                static fn (array $a): bool => ! empty($a['swatch_hex'])
            ));
            ?>
            <?php if ($swatches !== []): ?>
                <span class="rs-product__swatches" aria-label="Available in <?= count($swatches) ?> colours">
                    <?php foreach (array_slice($swatches, 0, 5) as $swatch): ?>
                        <span style="background: <?= esc($swatch['swatch_hex'], 'attr') ?>"
                              title="<?= esc($swatch['label'], 'attr') ?>"></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>


    <h3 class="rs-product__name">
        <a href="<?= $url ?>" class="after:absolute after:inset-0 hover:text-mulberry">
            <?= esc($product->name) ?>
        </a>
    </h3>

    <p class="rs-product__price num">
        <?php if ($isEnquire): ?>
            <span class="text-ink-muted">Price on enquiry</span>
        <?php else: ?>
            <?php if ($product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price): ?>
                <span class="rs-product__was"><?= rs_money($product->compare_at_price) ?></span>
            <?php endif; ?>
            <span class="font-semibold"><?= esc($product->formattedPrice()) ?></span>
        <?php endif; ?>
    </p>
</article>
