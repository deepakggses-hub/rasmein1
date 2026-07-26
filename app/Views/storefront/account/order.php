<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php $isEnquiry = $order['journey_mode'] === 'enquire_now'; ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6"><?= $isEnquiry ? 'Enquiry' : 'Order' ?></p>
        <h1 class="num mt-4 text-3xl sm:text-4xl"><?= esc($order['order_ref']) ?></h1>
        <p class="mt-3 text-ink-muted">
            Placed <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
            &middot; <span class="rs-badge rs-badge--soft"><?= esc($order['status']) ?></span>
        </p>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="space-y-6">
        <?php if ($shipment !== null && ! empty($shipment['tracking_number'])): ?>
            <section class="border border-brass bg-brass-soft/25 p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">On its way</h2>
                <p class="num mt-2 text-sm">
                    <?= esc($shipment['courier_name']) ?> &middot;
                    <span class="font-semibold"><?= esc($shipment['tracking_number']) ?></span>
                </p>
                <?php if (! empty($shipment['tracking_url'])): ?>
                    <a href="<?= esc($shipment['tracking_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"
                       class="rs-btn rs-btn--outline rs-btn--sm mt-3">Track this parcel</a>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-5 py-3 rs-eyebrow rs-eyebrow--plain">What you ordered</h2>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($items as $item): ?>
                    <li class="px-5 py-4">
                        <div class="num flex flex-wrap justify-between gap-x-4 gap-y-1">
                            <span class="font-medium"><?= esc($item['name_snapshot']) ?></span>
                            <span class="text-ink-muted">
                                &times; <?= (int) $item['quantity'] ?>
                                <span class="ml-3 font-semibold text-ink"><?= rs_money($item['line_total']) ?></span>
                            </span>
                        </div>
                        <?php if (! empty($components[(int) $item['id']])): ?>
                            <ul class="mt-2 border-l-2 border-brass/40 pl-3 text-sm text-ink-muted">
                                <?php foreach ($components[(int) $item['id']] as $component): ?>
                                    <li class="num flex justify-between gap-3">
                                        <span><?= esc($component['name_snapshot']) ?> &times; <?= (int) $component['quantity'] ?></span>
                                        <span><?= rs_money($component['line_total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (! empty($item['gift_message'])): ?>
                            <p class="mt-2 border-l-2 border-shell-line pl-3 text-sm italic text-ink-muted">
                                &ldquo;<?= esc($item['gift_message']) ?>&rdquo;
                            </p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="num ml-auto max-w-xs space-y-1.5 border-t border-shell-line px-5 py-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Subtotal</dt><dd><?= rs_money($order['subtotal']) ?></dd></div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="flex justify-between gap-4 text-pista-deep">
                        <dt>Discount</dt><dd>&minus;<?= rs_money($order['discount_total']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Delivery</dt><dd><?= rs_money($order['shipping_total']) ?></dd></div>
                <div class="flex justify-between gap-4 border-t border-shell-line pt-1.5 font-semibold">
                    <dt><?= $isEnquiry ? 'Estimated' : 'Total' ?></dt><dd><?= rs_money($order['grand_total']) ?></dd>
                </div>
            </dl>
        </section>

        <?php if (! empty($order['ship_line1'])): ?>
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Delivered to</h2>
                <address class="mt-3 text-sm leading-relaxed not-italic text-ink-soft">
                    <span class="font-semibold text-ink"><?= esc($order['ship_name']) ?></span><br>
                    <?= esc($order['ship_line1']) ?><br>
                    <?php if (! empty($order['ship_line2'])): ?><?= esc($order['ship_line2']) ?><br><?php endif; ?>
                    <?= esc($order['ship_city']) ?>, <?= esc($order['ship_state']) ?>
                    <span class="num"><?= esc($order['ship_postal_code']) ?></span>
                </address>
            </section>
        <?php endif; ?>

        <p class="text-sm text-ink-muted">
            Something not right? <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>?subject=<?= esc(rawurlencode($order['order_ref']), 'attr') ?>"
               class="rs-link text-mulberry font-medium">Write to us</a> quoting <?= esc($order['order_ref']) ?>.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
