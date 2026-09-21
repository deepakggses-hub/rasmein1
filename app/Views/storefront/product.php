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
                 wide screens and run horizontally below it on narrow ones â€” a
                 vertical strip on a phone steals the width the photograph needs.
                 They swap the main image with a script, but each thumbnail is a
                 real button so keyboard and screen-reader users reach every
                 view, and the first image renders with no script at all. */ ?>
        <div class="rs-gallery-wrap">
            <?php /* The frame is its own element so the share control can sit
                     ON the photograph while its menu still escapes: the picture
                     itself needs overflow:hidden to crop, and a popover inside
                     that would be clipped the moment it opened. */ ?>
            <div class="rs-gallery-main">
                <div class="relative aspect-[4/5] overflow-hidden bg-shell-deep">
                    <?php /* The hero image of the page, so it is eager and high
                             priority â€” lazy-loading the thing the visitor came to
                             see delays the only content that matters. */ ?>
                    <?= rs_picture($gallery[0]['path'] ?? null, '(min-width: 1024px) 46vw, 100vw', [
                        'id'      => 'product-image',
                        'alt'     => $gallery[0]['alt_text'] ?? $product->name,
                        'class'   => 'h-full w-full object-cover',
                        'width'   => '800',
                        'height'  => '1000',
                        'loading' => 'eager',
                        'fetchpriority' => 'high',
                    ]) ?>

                    <?php if ($product->hasDiscount()): ?>
                        <span class="rs-badge rs-badge--brass absolute left-4 top-4">
                            <?= $product->discountPercent() ?>% off
                        </span>
                    <?php endif; ?>
                </div>

                <?php /* ===================================== SHARE ==========
                 *
                 * Top right of the photograph, where every catalogue and app
                 * puts it â€” and where it is reachable without scrolling, which
                 * it was not down beside the description.
                 *
                 * ONE button. The native sheet where the browser has one; a
                 * <details> holding the destinations where it does not, so the
                 * control still works with no JavaScript at all.
                 */ ?>
                <?php $shareUrl = current_url(); ?>
                <div class="rs-share rs-share--onimage" data-share
                     data-share-title="<?= esc((string) $product->name, 'attr') ?>"
                     data-share-text="<?= esc(rs_excerpt((string) ($product->short_description ?? ''), 140), 'attr') ?>"
                     data-share-url="<?= esc($shareUrl, 'attr') ?>">

                    <button type="button" class="rs-sharebtn" data-share-native hidden
                            aria-label="Share this piece">
                        <?= rs_icon('share-nodes', 'rs-sharebtn__icon') ?>
                    </button>

                    <details class="rs-sharepop" data-share-fallback>
                        <summary class="rs-sharebtn" aria-label="Share this piece">
                            <?= rs_icon('share-nodes', 'rs-sharebtn__icon') ?>
                        </summary>

                        <div class="rs-sharepop__menu">
                            <a href="https://wa.me/?text=<?= urlencode($product->name . ' â€” ' . $shareUrl) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="rs-sharelink rs-sharelink--wa" aria-label="Share on WhatsApp">
                                <?= rs_icon('whatsapp-mark', 'rs-sharelink__icon') ?>
                            </a>

                            <a href="https://www.pinterest.com/pin/create/button/?url=<?= urlencode($shareUrl) ?>&description=<?= urlencode((string) $product->name) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="rs-sharelink" aria-label="Save to Pinterest">
                                <?= rs_icon('pinterest-mark', 'rs-sharelink__icon') ?>
                            </a>

                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="rs-sharelink" aria-label="Share on Facebook">
                                <?= rs_icon('facebook-mark', 'rs-sharelink__icon') ?>
                            </a>

                            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode((string) $product->name) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="rs-sharelink" aria-label="Share on X">
                                <?= rs_icon('x-mark', 'rs-sharelink__icon') ?>
                            </a>

                            <a href="mailto:?subject=<?= urlencode((string) $product->name) ?>&body=<?= urlencode($product->name . ' â€” ' . $shareUrl) ?>"
                               class="rs-sharelink" aria-label="Share by email">
                                <?= rs_icon('mail', 'rs-sharelink__icon') ?>
                            </a>

                            <button type="button" class="rs-sharelink" data-copy="<?= esc($shareUrl, 'attr') ?>"
                                    aria-label="Copy link">
                                <?= rs_icon('link', 'rs-sharelink__icon') ?>
                                <span class="rs-sharelink__said" data-copy-label></span>
                            </button>
                        </div>
                    </details>
                </div>
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
                                <?php /* Decorative: the button's aria-label already announces which
                                         photograph this is, so a described alt would be
                                         read twice. */ ?>
                                <?= rs_picture($image['path'] ?? null, '96px', ['alt' => '']) ?>
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
             * shop types per product ("WEDDING Â· SIGNATURE HAMPER"). Either half
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
             * every product name up by hand â€” and fall back to the plain
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
                    SKU <span data-variant-sku><?= esc($chosen['sku'] ?? $product->sku) ?></span>
                </span>
            </div>

            <!-- Price. The variant's if it sets one, otherwise the product's. -->
            <p class="num mt-6 flex flex-wrap items-baseline gap-3">
                <span class="font-display text-3xl font-semibold text-mulberry" data-variant-price>
                    <?= esc(isset($chosen['price']) ? rs_money($chosen['price']) : $product->formattedPrice()) ?>
                </span>
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
                            <span class="<?= $i <= $stars ? '' : 'opacity-30' ?>"><?= rs_icon('star', 'rs-star') ?></span>
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
                    <span class="text-bad font-medium">Sold out â€” tell us and we'll let you know when it's back</span>
                <?php endif; ?>
            </p>

            <?php if ($product->short_description !== null && $product->short_description !== ''): ?>
                <p class="mt-6 leading-relaxed text-ink-soft"><?= esc($product->short_description) ?></p>
            <?php endif; ?>

            <hr class="rs-rule my-8">

            <!-- Primary action. A real form: works without JavaScript, and the
                 quantity is re-clamped server-side against live stock.

                 data-cta-anchor goes on the BUTTONS, never on this wrapper.
                 The wrapper also holds the variant chips, the specification
                 list, the gift-box link and the customisation line, so it
                 stays on screen long after the buttons have gone — which made
                 the sticky bar vanish exactly when it was most needed. -->
            <div class="space-y-3">
                <?php if (! $product->inStock()): ?>
                    <span class="rs-btn rs-btn--outline w-full" aria-disabled="true" data-cta-anchor>Sold out</span>
                <?php else: ?>
                    <?php /* A real form throughout: the stepper buttons are an
                             enhancement, and the number input still works when
                             the script does not load. Quantity is re-clamped
                             server-side against live stock regardless. */ ?>

            <?php /* ============================== variants ============================== */ ?>
            <?php if (($variantGroups ?? []) !== []): ?>
                <?php
                /*
                 * The whole matrix goes to the browser as JSON.
                 *
                 * Selecting a colour has to grey out the sizes that colour does
                 * not come in â€” Amazon's behaviour, and the thing plain
                 * attribute chips cannot do. Asking the server on every click
                 * would make the page feel like dial-up; the combinations are a
                 * few dozen rows, so they travel once.
                 */
                $payload = [
                    'base'     => site_url('product/' . $product->slug),
                    'chosen'   => $chosen['key'] ?? null,
                    'variants' => array_map(static function (array $v) use ($product): array {
                        return [
                            'id'     => (int) $v['id'],
                            'key'    => $v['variant_key'],
                            'label'  => $v['label'],
                            'sku'    => $v['sku'],
                            // Formatted here, where the currency rules live â€”
                            // the browser must not reimplement money.
                            'price'  => rs_money($v['price'] !== null ? (float) $v['price'] : (float) $product->price),
                            'image'  => $v['image'] ? rs_image($v['image'], 'content') : null,
                            'values' => array_values($v['values']),
                            'stock'  => (int) $v['stock_qty'],
                        ];
                    }, $variants),
                ];
                ?>
                <div class="mt-8 grid gap-5" data-variants
                     data-matrix="<?= esc(json_encode($payload), 'attr') ?>">
                    <?php foreach ($variantGroups as $group): ?>
                        <div>
                            <span class="rs-kicker">
                                <?= esc($group['name']) ?>
                                <?php /* The chosen one, named â€” a swatch alone
                                         does not tell anyone it is "Antique
                                         Silver" rather than grey. */ ?>
                                <span class="ml-2 text-ink-soft normal-case tracking-normal"
                                      data-variant-chosen="<?= esc($group['code'], 'attr') ?>"></span>
                            </span>

                            <ul class="mt-3 flex flex-wrap gap-2" role="radiogroup"
                                aria-label="<?= esc($group['name'], 'attr') ?>">
                                <?php foreach ($group['values'] as $value): ?>
                                    <li>
                                        <label class="rs-attr <?= $group['input_type'] === 'swatch' ? 'rs-attr--swatch' : '' ?>"
                                               data-variant-option
                                               data-code="<?= esc($group['code'], 'attr') ?>"
                                               data-value="<?= (int) $value['id'] ?>">
                                            <input type="radio" class="sr-only"
                                                   name="v_<?= esc($group['code'], 'attr') ?>"
                                                   value="<?= (int) $value['id'] ?>">
                                            <?php if ($group['input_type'] === 'swatch' && ! empty($value['swatch'])): ?>
                                                <span class="rs-attr__chip"
                                                      style="background: <?= esc($value['swatch'], 'attr') ?>"></span>
                                            <?php endif; ?>
                                            <span><?= esc($value['label']) ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php /* Attributes that are NOT choices â€” the size a piece simply
                     is, its finish â€” listed rather than offered. */ ?>
            <?php if (($attributes ?? []) !== []): ?>
                <dl class="mt-7 grid gap-2 border-t border-shell-line pt-6 text-sm">
                    <?php foreach ($attributes as $group): ?>
                        <?php if ($group['selectable']) { continue; } ?>
                        <div class="flex gap-3">
                            <dt class="w-28 shrink-0 text-ink-muted"><?= esc($group['name']) ?></dt>
                            <dd class="text-ink-soft" data-spec="<?= esc($group['code'], 'attr') ?>">
                                <?= esc(implode(' Â· ', array_column($group['values'], 'label'))) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

                    <?php /* data-cart makes this an in-place add, like the cards. Without
                             JavaScript it still posts normally. */ ?>
                    <?php if (rs_is_enquire_mode($product->sale_mode ?? 'inherit')): ?>
                        <?php /* Corporate mode: quote, not checkout. */ ?>
                        <button type="button" class="rs-btn rs-btn--primary w-full"
                                data-cta-anchor
                                data-bulk-enquiry
                                data-product-id="<?= (int) $product->id ?>"
                                data-product-name="<?= esc($product->name, 'attr') ?>">
                            <?= rs_icon('briefcase', 'h-4 w-4') ?>
                            Request a bulk quote
                        </button>
                    <?php else: ?>
                    <form id="rs-add" method="post" action="<?= site_url('cart/add') ?>" class="space-y-4" data-cart>
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                    <input type="hidden" name="variant_id" data-variant-id
                           value="<?= (int) ($chosen['id'] ?? 0) ?>">
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

                        <div class="grid gap-3 sm:grid-cols-2" data-cta-anchor>
                            <button type="submit" class="rs-btn rs-btn--primary w-full">
                                <?= esc($product->ctaLabel('add')) ?>
                            </button>
                            <button type="submit" name="checkout" value="1" class="rs-btn rs-btn--gold w-full">
                                Buy now
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--outline w-full">
                    Put this in a gift box
                </a>

                <?php /* Customisation is a conversation, not a form.
                 *
                 * The number comes from Shop identity via service('brand'), the
                 * same source the footer and every lead form read, so the shop
                 * changes it in ONE place. Three page templates once each asked
                 * for their own WhatsApp number and forgetting one left a live
                 * button pointing at a dead line.
                 *
                 * Hidden entirely when no number is set: "please connect us on
                 * WhatsApp" with nothing to click is worse than saying nothing.
                 */ ?>
                <?php $rsWhatsApp = (string) preg_replace('/\D/', '', (string) service('brand')->whatsapp); ?>
                <?php if ($rsWhatsApp !== ''): ?>
                    <?php /* Icon BESIDE the sentence, not instead of it. A bare
                             WhatsApp glyph says "there is a chat somewhere"; it
                             does not say what to chat about, and on a page of
                             buttons an unlabelled icon is the one thing nobody
                             clicks. The icon is aria-hidden because the text
                             already carries the whole meaning. */ ?>
                    <a href="https://wa.me/<?= $rsWhatsApp ?>" target="_blank" rel="noopener noreferrer"
                       class="rs-wa">
                        <span class="rs-wa__icon" aria-hidden="true"><?= rs_icon('whatsapp', '') ?></span>
                        <span class="rs-wa__text">
                            For Customisation, please connect us on
                            <strong class="rs-wa__cta">WhatsApp</strong>
                        </span>
                    </a>
                <?php endif; ?>
            </div>


            <?php /* The three promises from the design. Content, not chrome â€”
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
                        This item is quoted rather than sold online â€” tell us the quantity
                        and we'll come back with a price.
                    </span>
                </p>
            <?php endif; ?>

            <?php
            /*
             * The panels from the design.
             *
             * Native <details>, so they work with no JavaScript and are announced
             * correctly. A panel with nothing in it is NOT rendered â€” an empty
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
                                <?php /* Specifications are derived, not typed â€” SKU,
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

<?php /* ================================================ STICKY CTA BAR =====
 *
 * Shown only once the real call to action has scrolled out of the fold, on
 * every width â€” the page is long, and on a phone the buttons leave the screen
 * within one swipe of the gallery.
 *
 * THE BUTTONS ARE THE SAME FORM.
 *
 * `form="rs-add"` associates a button with a form it is not inside, so these
 * submit the REAL one: the chosen variant, the chosen quantity and the CSRF
 * token all travel, and the existing [data-cart] handler picks it up with
 * `e.submitter` exactly as it does for the buttons in the column. A second
 * form here would be a second copy of that state, and it would drift the first
 * time someone changed a variant.
 *
 * It renders for every state the column can be in â€” sold out, bulk enquiry,
 * normal â€” because a bar that silently disappears on some products reads as a
 * fault, and one that offers Add to cart for something sold out is worse.
 *
 * hidden by default, and the script is what reveals it. With no JavaScript
 * there is no observer to say whether the real buttons are on screen, and a
 * bar permanently covering the foot of the page would be in the way.
 */ ?>
<div class="rs-stickycta" data-sticky-cta hidden>
    <div class="rs-stickycta__inner">
        <?php /* The thumbnail and name, so it is obvious what is being added
                 after three screens of scrolling. Hidden on the narrowest
                 phones, where the button needs the whole width. */ ?>
        <div class="rs-stickycta__what">
            <?= rs_picture($gallery[0]['path'] ?? null, '56px', [
                'alt'   => '',
                'class' => 'rs-stickycta__img',
            ]) ?>
            <div class="rs-stickycta__text">
                <p class="rs-stickycta__name"><?= esc(rs_excerpt((string) $product->name, 48)) ?></p>
                <p class="num rs-stickycta__price" data-variant-price>
                    <?= esc(isset($chosen['price']) ? rs_money($chosen['price']) : $product->formattedPrice()) ?>
                </p>
            </div>
        </div>

        <div class="rs-stickycta__actions">
            <?php if (! $product->inStock()): ?>
                <span class="rs-btn rs-btn--outline" aria-disabled="true">Sold out</span>
            <?php elseif (rs_is_enquire_mode($product->sale_mode ?? 'inherit')): ?>
                <button type="button" class="rs-btn rs-btn--primary"
                        data-bulk-enquiry
                        data-product-id="<?= (int) $product->id ?>"
                        data-product-name="<?= esc($product->name, 'attr') ?>">
                    Request a bulk quote
                </button>
            <?php else: ?>
                <button type="submit" form="rs-add" class="rs-btn rs-btn--primary">
                    <?= esc($product->ctaLabel('add')) ?>
                </button>
                <button type="submit" form="rs-add" name="checkout" value="1"
                        class="rs-btn rs-btn--gold rs-stickycta__buy">
                    Buy now
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

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
