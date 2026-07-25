<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * The cart — or the enquiry list, when the journey is Enquire. Same rows, same
 * page; the vocabulary and the final button change.
 *
 * @var array<string, mixed> $snapshot
 */
$lines     = $snapshot['lines'];
$isEnquiry = $snapshot['journey_mode'] === \Config\Rasmein::MODE_ENQUIRE;
$blocking  = $snapshot['blocking'] !== [];
$issuesBy  = [];

foreach ($snapshot['issues'] as $issue) {
    $issuesBy[$issue['line_id']][] = $issue;
}

// Why is this an enquiry when the site is in Buy mode? Because something in
// the basket has to be quoted. Say so rather than leaving it a mystery.
$forcedByItem = $isEnquiry && ! rs_is_enquire_mode();
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6"><?= $isEnquiry ? 'Your enquiry' : 'Your cart' ?></p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">
            <?= $isEnquiry ? 'Ready to be quoted' : 'Ready when you are' ?>
        </h1>
    </div>
</header>

<div class="rs-shell py-10 lg:py-14">

<?php if ($snapshot['is_empty']): ?>
    <div class="py-16 text-center">
        <div class="mx-auto max-w-52">
            <?= view('partials/tray', ['capacity' => 6, 'filled' => [], 'columns' => 3]) ?>
        </div>
        <h2 class="mt-10 text-2xl">Nothing in here yet.</h2>
        <p class="mx-auto mt-3 max-w-sm text-ink-muted">
            Start with a box and fill it yourself, or pick something from the shop.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--primary">Build a gift box</a>
            <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--outline">Browse the shop</a>
        </div>
    </div>
<?php else: ?>

    <?php if ($forcedByItem): ?>
        <p class="mb-8 flex flex-wrap items-center gap-3 border border-pista/40 bg-pista/10 px-4 py-3 text-sm">
            <span class="rs-badge rs-badge--enquire shrink-0">Quoted basket</span>
            <span class="text-ink-soft">
                Something here is priced per brief, so we will quote the whole basket
                rather than charge for part of it. Nothing is paid online.
            </span>
        </p>
    <?php endif; ?>

    <div class="lg:grid lg:grid-cols-[1fr_22rem] lg:gap-12 lg:items-start">

        <!-- ------------------------------------------------------- lines -->
        <section aria-label="Items">
            <ul class="divide-y divide-shell-line border-y border-shell-line">
                <?php foreach ($lines as $line): ?>
                    <li class="py-6">
                        <div class="flex gap-4 sm:gap-6">
                            <!-- thumbnail -->
                            <div class="h-24 w-20 shrink-0 overflow-hidden border border-shell-line bg-shell-deep sm:h-28 sm:w-24">
                                <?php if ($line['type'] === 'gift_box'): ?>
                                    <img src="<?= esc(rs_image(null, 'boxes'), 'attr') ?>" alt=""
                                         class="h-full w-full object-cover">
                                <?php else: ?>
                                    <img src="<?= esc(rs_image($line['image'], 'products'), 'attr') ?>" alt=""
                                         class="h-full w-full object-cover">
                                <?php endif; ?>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                    <div class="min-w-0">
                                        <h2 class="text-base font-semibold leading-snug">
                                            <?php if ($line['type'] === 'product' && $line['slug'] !== null): ?>
                                                <a href="<?= site_url('product/' . $line['slug']) ?>" class="rs-link">
                                                    <?= esc($line['name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?= esc($line['name']) ?>
                                            <?php endif; ?>
                                        </h2>
                                        <p class="mt-1 flex flex-wrap items-center gap-2 font-mono text-[0.625rem] tracking-[0.12em] text-ink-muted uppercase">
                                            <?php if ($line['unit_label'] !== null): ?>
                                                <span><?= esc($line['unit_label']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($line['sku'] !== ''): ?>
                                                <span><?= esc($line['sku']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($line['sale_mode'] === \Config\Rasmein::MODE_ENQUIRE): ?>
                                                <span class="rs-badge rs-badge--enquire">Quoted</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <p class="num text-right">
                                        <span class="font-semibold"><?= rs_money($line['line_total']) ?></span>
                                        <?php if ($line['quantity'] > 1): ?>
                                            <span class="mt-0.5 block text-xs text-ink-muted">
                                                <?= rs_money($line['unit_price']) ?> each
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <!-- gift box contents -->
                                <?php if ($line['type'] === 'gift_box'): ?>
                                    <div class="mt-3 border-l-2 border-brass/40 pl-4">
                                        <p class="num font-mono text-[0.625rem] tracking-[0.12em] text-brass uppercase">
                                            <?= (int) $line['slots_used'] ?> of <?= (int) $line['capacity'] ?> compartments
                                        </p>
                                        <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                                            <?php foreach ($line['components'] as $component): ?>
                                                <li class="num flex justify-between gap-4">
                                                    <span><?= esc($component['name']) ?> &times; <?= (int) $component['quantity'] ?></span>
                                                    <span><?= rs_money($component['line_total']) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php foreach ($line['breakdown'] as $entry): ?>
                                            <p class="mt-1.5 text-xs text-pista-deep"><?= esc($entry['label']) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (! empty($line['gift_message'])): ?>
                                    <p class="mt-3 border-l-2 border-shell-line pl-3 text-sm italic text-ink-muted">
                                        &ldquo;<?= esc(rs_excerpt($line['gift_message'], 120)) ?>&rdquo;
                                    </p>
                                <?php endif; ?>

                                <!-- per-line problems -->
                                <?php foreach ($issuesBy[$line['line_id']] ?? [] as $issue): ?>
                                    <p class="mt-3 text-sm font-medium <?= $issue['severity'] === 'blocking' ? 'text-bad' : 'text-warn' ?>">
                                        <?= esc($issue['message']) ?>
                                    </p>
                                <?php endforeach; ?>

                                <!-- quantity + remove -->
                                <div class="mt-4 flex flex-wrap items-center gap-4">
                                    <form method="post" action="<?= site_url('cart/update') ?>" class="flex items-center gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="line_id" value="<?= (int) $line['line_id'] ?>">
                                        <input type="hidden" name="return_to" value="cart">
                                        <label class="sr-only" for="qty-<?= (int) $line['line_id'] ?>">
                                            Quantity for <?= esc($line['name']) ?>
                                        </label>
                                        <input id="qty-<?= (int) $line['line_id'] ?>" type="number" name="quantity"
                                               class="rs-input num w-20 py-1.5 text-center"
                                               value="<?= (int) $line['quantity'] ?>" min="1" max="99" inputmode="numeric">
                                        <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Update</button>
                                    </form>

                                    <form method="post" action="<?= site_url('cart/remove') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="line_id" value="<?= (int) $line['line_id'] ?>">
                                        <input type="hidden" name="return_to" value="cart">
                                        <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="mt-6">
                <a href="<?= site_url('shop') ?>" class="rs-link text-sm text-ink-muted">
                    <span aria-hidden="true">&larr;</span> Keep shopping
                </a>
            </div>
        </section>

        <!-- ------------------------------------------------------ totals -->
        <aside class="mt-10 lg:mt-0 lg:sticky lg:top-32">
            <div class="border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Summary</h2>

                <dl class="num mt-5 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Subtotal</dt>
                        <dd><?= rs_money($snapshot['subtotal']) ?></dd>
                    </div>

                    <?php if ($snapshot['discount_total'] > 0): ?>
                        <div class="flex justify-between gap-4 text-pista-deep">
                            <dt>Discount<?= $snapshot['coupon'] !== null ? ' (' . esc($snapshot['coupon']['code']) . ')' : '' ?></dt>
                            <dd>&minus;<?= rs_money($snapshot['discount_total']) ?></dd>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Delivery</dt>
                        <dd>
                            <?= $snapshot['shipping_total'] > 0
                                ? rs_money($snapshot['shipping_total'])
                                : '<span class="text-pista-deep">Free</span>' ?>
                        </dd>
                    </div>

                    <?php if ($snapshot['tax_total'] > 0): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Tax</dt>
                            <dd><?= rs_money($snapshot['tax_total']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if ($snapshot['free_shipping']['threshold'] > 0 && ! $snapshot['free_shipping']['earned']): ?>
                    <p class="mt-4 border-t border-shell-line pt-4 text-xs text-ink-muted">
                        Add <span class="num font-semibold text-ink"><?= rs_money($snapshot['free_shipping']['remaining']) ?></span>
                        more for free delivery.
                    </p>
                <?php endif; ?>

                <hr class="rs-rule my-5">

                <div class="num flex items-baseline justify-between gap-4">
                    <p class="font-semibold"><?= $isEnquiry ? 'Estimated total' : 'Total' ?></p>
                    <p class="font-display text-2xl font-semibold text-mulberry">
                        <?= rs_money($snapshot['grand_total']) ?>
                    </p>
                </div>

                <?php if ($isEnquiry): ?>
                    <p class="rs-help mt-2">Indicative only. Your quote will confirm the final price.</p>
                <?php endif; ?>

                <?php if ($blocking): ?>
                    <p class="mt-5 text-sm font-medium text-bad">
                        Sort out the items flagged above to continue.
                    </p>
                    <span class="rs-btn rs-btn--primary mt-3 w-full" aria-disabled="true">
                        <?= $isEnquiry ? 'Send enquiry' : 'Checkout' ?>
                    </span>
                <?php else: ?>
                    <a href="<?= site_url('checkout') ?>" class="rs-btn rs-btn--primary mt-5 w-full">
                        <?= $isEnquiry ? 'Send enquiry' : 'Continue to checkout' ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- ------------------------------------------------- coupon -->
            <div class="mt-5 border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Coupon</h2>

                <?php if ($snapshot['coupon'] !== null): ?>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-sm">
                            <span class="rs-badge rs-badge--brass"><?= esc($snapshot['coupon']['code']) ?></span>
                            <?php if (! empty($snapshot['coupon']['description'])): ?>
                                <span class="mt-1.5 block text-xs text-ink-muted">
                                    <?= esc($snapshot['coupon']['description']) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <form method="post" action="<?= site_url('cart/coupon/remove') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="cart">
                            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= site_url('cart/coupon') ?>" class="mt-4 flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="cart">
                        <label class="flex-1">
                            <span class="sr-only">Coupon code</span>
                            <input type="text" name="code" class="rs-input uppercase" placeholder="Enter code"
                                   autocapitalize="characters" autocomplete="off" maxlength="40">
                        </label>
                        <button type="submit" class="rs-btn rs-btn--outline">Apply</button>
                    </form>
                    <?php if ($snapshot['coupon_error'] !== null): ?>
                        <p class="rs-error"><?= esc($snapshot['coupon_error']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
<?php endif; ?>
</div>

<?= $this->endSection() ?>
