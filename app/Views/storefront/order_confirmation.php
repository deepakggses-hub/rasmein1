<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var array<string, mixed> $order
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<int, array<string, mixed>>> $components
 * @var bool $isEnquiry
 * @var array<string, mixed>|null $enquiry
 */
$reference = $isEnquiry && $enquiry !== null ? $enquiry['enquiry_ref'] : $order['order_ref'];
?>

<section class="border-b border-shell-line bg-mulberry-deep text-shell">
    <div class="rs-shell py-14 lg:py-18">
        <p class="rs-eyebrow rs-eyebrow--on-dark">
            <?= $isEnquiry ? 'Enquiry received' : 'Order placed' ?>
        </p>
        <h1 class="mt-5 max-w-2xl text-4xl sm:text-5xl">
            <?= $isEnquiry ? 'Thank you — we have it.' : 'Thank you. It is in the queue.' ?>
        </h1>
        <p class="mt-5 max-w-xl leading-relaxed text-shell/80">
            <?php if ($isEnquiry): ?>
                A person will read this and come back with a written quote, usually
                within one working day. Nothing has been charged.
            <?php else: ?>
                We have emailed a copy to <?= esc($order['customer_email']) ?>.
                <?php if ($order['payment_status'] === 'unpaid'): ?>
                    We will be in touch to arrange payment before the box is dispatched.
                <?php endif; ?>
            <?php endif; ?>
        </p>

        <dl class="mt-9 flex flex-wrap gap-x-12 gap-y-4 border-t border-brass/25 pt-6">
            <div>
                <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">Reference</dt>
                <dd class="num mt-1 font-display text-xl font-semibold"><?= esc($reference) ?></dd>
            </div>
            <div>
                <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">
                    <?= $isEnquiry ? 'Estimated' : 'Total' ?>
                </dt>
                <dd class="num mt-1 font-display text-xl font-semibold"><?= rs_money($order['grand_total']) ?></dd>
            </div>
            <div>
                <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">Placed</dt>
                <dd class="mt-1 font-display text-xl font-semibold">
                    <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                </dd>
            </div>
        </dl>
    </div>
</section>

<div class="rs-shell py-12 lg:py-16">
    <div class="lg:grid lg:grid-cols-[1fr_20rem] lg:gap-12 lg:items-start">

        <section>
            <h2 class="rs-eyebrow">What you asked for</h2>

            <ul class="mt-6 divide-y divide-shell-line border-y border-shell-line">
                <?php foreach ($items as $item): ?>
                    <li class="py-5">
                        <div class="num flex flex-wrap justify-between gap-x-4 gap-y-1">
                            <div class="min-w-0">
                                <p class="font-semibold"><?= esc($item['name_snapshot']) ?></p>
                                <p class="mt-1 font-mono text-[0.625rem] tracking-[0.12em] text-ink-muted uppercase">
                                    <?php if ($item['sku_snapshot'] !== null): ?>
                                        <?= esc($item['sku_snapshot']) ?> &middot;
                                    <?php endif; ?>
                                    Qty <?= (int) $item['quantity'] ?>
                                </p>
                            </div>
                            <p class="font-semibold"><?= rs_money($item['line_total']) ?></p>
                        </div>

                        <?php if (! empty($components[(int) $item['id']])): ?>
                            <ul class="mt-3 border-l-2 border-brass/40 pl-4 text-sm text-ink-muted">
                                <?php foreach ($components[(int) $item['id']] as $component): ?>
                                    <li class="num flex justify-between gap-4">
                                        <span><?= esc($component['name_snapshot']) ?> &times; <?= (int) $component['quantity'] ?></span>
                                        <span><?= rs_money($component['line_total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (! empty($item['gift_message'])): ?>
                            <p class="mt-3 border-l-2 border-shell-line pl-3 text-sm italic text-ink-muted">
                                &ldquo;<?= esc($item['gift_message']) ?>&rdquo;
                            </p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dl class="num mt-6 ml-auto max-w-xs space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">Subtotal</dt>
                    <dd><?= rs_money($order['subtotal']) ?></dd>
                </div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="flex justify-between gap-4 text-pista-deep">
                        <dt>Discount<?= $order['coupon_code'] !== null ? ' (' . esc($order['coupon_code']) . ')' : '' ?></dt>
                        <dd>&minus;<?= rs_money($order['discount_total']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">Delivery</dt>
                    <dd><?= (float) $order['shipping_total'] > 0 ? rs_money($order['shipping_total']) : 'Free' ?></dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-shell-line pt-2 font-semibold">
                    <dt><?= $isEnquiry ? 'Estimated' : 'Total' ?></dt>
                    <dd><?= rs_money($order['grand_total']) ?></dd>
                </div>
            </dl>
        </section>

        <aside class="mt-12 lg:mt-0">
            <?php if (! $isEnquiry): ?>
                <div class="border border-shell-line bg-white p-6">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Going to</h2>
                    <address class="mt-4 text-sm leading-relaxed not-italic text-ink-soft">
                        <span class="block font-semibold text-ink"><?= esc($order['ship_name']) ?></span>
                        <?= esc($order['ship_line1']) ?><br>
                        <?php if (! empty($order['ship_line2'])): ?><?= esc($order['ship_line2']) ?><br><?php endif; ?>
                        <?= esc($order['ship_city']) ?>, <?= esc($order['ship_state']) ?><br>
                        <span class="num"><?= esc($order['ship_postal_code']) ?></span><br>
                        <?= esc($order['ship_country']) ?>
                        <span class="num mt-2 block"><?= esc($order['ship_phone']) ?></span>
                    </address>
                </div>
            <?php endif; ?>

            <div class="mt-5 border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">What happens next</h2>
                <ol class="mt-4 space-y-3 text-sm text-ink-soft">
                    <?php
                    $steps = $isEnquiry
                        ? ['We read your brief properly.',
                           'You get a written quote, usually within one working day.',
                           'Approve it and we schedule the run.']
                        : ['We confirm stock and pack your box by hand.',
                           'It is dispatched within 48 hours.',
                           'You get a tracking link by email and SMS.'];
                    foreach ($steps as $index => $step):
                    ?>
                        <li class="flex gap-3">
                            <span class="num shrink-0 font-mono text-[0.6875rem] text-brass">
                                <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>
                            </span>
                            <span><?= esc($step) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="mt-5 text-sm">
                <p class="text-ink-muted">
                    Questions about <span class="num font-semibold text-ink"><?= esc($reference) ?></span>?
                    <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>?subject=<?= esc(rawurlencode($reference), 'attr') ?>"
                       class="rs-link text-mulberry font-medium">Write to us</a>
                    and quote that reference.
                </p>
            </div>

            <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--outline mt-6 w-full">Keep shopping</a>
        </aside>
    </div>
</div>

<?= $this->endSection() ?>
