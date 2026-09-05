<?php
/**
 * The cart drawer's contents.
 *
 * Rendered by the SERVER and fetched whole, rather than assembled in the
 * browser from JSON. Coupons, shipping bands and gift-box pricing all live in
 * PricingService; rebuilding any of that in JavaScript is a second
 * implementation that eventually disagrees with the first.
 *
 * @var array $snapshot
 */
$lines = $snapshot['lines'] ?? [];
?>

<?php if ($lines === []): ?>
    <div class="grid flex-1 place-items-center px-6 py-16 text-center">
        <div>
            <p class="font-display text-xl">Nothing here yet.</p>
            <p class="rs-help mt-2">Pieces you add will gather here.</p>
            <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--primary mt-6">Browse the shop</a>
        </div>
    </div>
<?php else: ?>
    <ul class="flex-1 divide-y divide-shell-line overflow-y-auto px-6">
        <?php foreach ($lines as $line): ?>
            <li class="flex gap-4 py-5">
                <a href="<?= $line['slug'] ? site_url('product/' . $line['slug']) : '#' ?>"
                   class="block h-20 w-20 shrink-0 overflow-hidden bg-shell-deep">
                    <?php if (! empty($line['image'])): ?>
                        <img src="<?= rs_image((string) $line['image'], 'thumb') ?>" alt=""
                             class="h-full w-full object-cover">
                    <?php endif; ?>
                </a>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <?php if (! empty($line['eyebrow'])): ?>
                                <p class="rs-kicker"><?= esc($line['eyebrow']) ?></p>
                            <?php endif; ?>

                            <p class="mt-1 font-display text-[0.9375rem] leading-snug">
                                <?= esc($line['name']) ?>
                            </p>

                            <?php if (! empty($line['chosen_attributes'])): ?>
                                <p class="rs-help"><?= esc($line['chosen_attributes']) ?></p>
                            <?php endif; ?>
                        </div>

                        <p class="num shrink-0 text-sm"><?= rs_money($line['line_total']) ?></p>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <?php /* The same endpoint the cart page uses, so one
                                 quantity rule governs both. */ ?>
                        <form method="post" action="<?= site_url('cart/update') ?>" class="rs-qtymini" data-drawer-qty>
                            <?= csrf_field() ?>
                            <input type="hidden" name="line_id" value="<?= (int) $line['line_id'] ?>">

                            <button type="submit" name="quantity" value="<?= max(0, (int) $line['quantity'] - 1) ?>"
                                    class="rs-qtymini__btn" aria-label="One fewer">&minus;</button>

                            <span class="num" aria-live="polite"><?= (int) $line['quantity'] ?></span>

                            <button type="submit" name="quantity" value="<?= (int) $line['quantity'] + 1 ?>"
                                    class="rs-qtymini__btn" aria-label="One more">+</button>
                        </form>

                        <form method="post" action="<?= site_url('cart/remove') ?>" data-drawer-qty>
                            <?= csrf_field() ?>
                            <input type="hidden" name="line_id" value="<?= (int) $line['line_id'] ?>">
                            <button type="submit" class="rs-kicker text-ink-muted hover:text-bad">Remove</button>
                        </form>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="border-t border-shell-line bg-shell-deep px-6 py-5">
        <dl class="grid gap-2 text-sm">
            <div class="flex items-center justify-between">
                <dt>Subtotal</dt>
                <dd class="num"><?= rs_money($snapshot['subtotal']) ?></dd>
            </div>

            <div class="flex items-center justify-between text-ink-muted">
                <dt>Shipping</dt>
                <dd><?php /* Not computed here: it depends on the delivery
                             address, which is asked for at checkout. Guessing a
                             figure that then changes is worse than saying so. */ ?>
                    Calculated at checkout</dd>
            </div>

            <div class="mt-2 flex items-center justify-between border-t border-shell-line pt-3">
                <dt class="font-display text-lg">Total</dt>
                <dd class="num font-display text-lg"><?= rs_money($snapshot['subtotal']) ?></dd>
            </div>
        </dl>

        <a href="<?= site_url('checkout') ?>" class="rs-btn rs-btn--primary mt-5 w-full">
            <?php /* Not rs_cta_label(): that returns "Buy now", which reads
                     oddly beside a total. In an enquiry basket it is a
                     different act, so the label follows the journey. */ ?>
            <?= rs_is_enquire_mode() ? 'Send the enquiry' : 'Proceed to checkout' ?>
            <span aria-hidden="true">&rarr;</span>
        </a>

        <a href="<?= site_url('cart') ?>" class="rs-link mt-3 block text-center text-xs text-ink-muted">
            View the full basket
        </a>
    </div>
<?php endif; ?>
