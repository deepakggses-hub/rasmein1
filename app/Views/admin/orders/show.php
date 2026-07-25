<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array<string, mixed> $order
 * @var list<string> $nextStates
 * @var bool $canManage
 */
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Order',
    'heading'    => $order['order_ref'],
    'subheading' => 'Placed ' . date('j M Y, H:i', strtotime((string) $order['placed_at']))
        . ' · ' . $order['customer_name'],
    'actions'    => '<a href="' . site_url('admin/orders') . '" class="rs-btn rs-btn--outline rs-btn--sm">All orders</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_20rem] lg:px-8">
    <div class="space-y-6">

        <!-- Items -->
        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                Items
            </h2>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($items as $item): ?>
                    <li class="px-4 py-3">
                        <div class="num flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                            <span class="min-w-0">
                                <span class="font-medium"><?= esc($item['name_snapshot']) ?></span>
                                <?php if ($item['sku_snapshot'] !== null): ?>
                                    <span class="ml-1.5 font-mono text-[0.625rem] text-ink-muted"><?= esc($item['sku_snapshot']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="text-ink-muted">
                                <?= rs_money($item['unit_price']) ?> × <?= (int) $item['quantity'] ?>
                                <span class="ml-3 font-semibold text-ink"><?= rs_money($item['line_total']) ?></span>
                            </span>
                        </div>

                        <?php if (! empty($components[(int) $item['id']])): ?>
                            <ul class="mt-2 border-l-2 border-brass/40 pl-3 text-xs text-ink-muted">
                                <?php foreach ($components[(int) $item['id']] as $component): ?>
                                    <li class="num flex justify-between gap-3">
                                        <span><?= esc($component['name_snapshot']) ?> × <?= (int) $component['quantity'] ?></span>
                                        <span><?= rs_money($component['line_total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (! empty($item['gift_message'])): ?>
                            <p class="mt-2 border-l-2 border-shell-line pl-3 text-xs italic text-ink-muted">
                                &ldquo;<?= esc($item['gift_message']) ?>&rdquo;
                            </p>
                        <?php endif; ?>
                        <?php if (! empty($item['special_note'])): ?>
                            <p class="mt-1.5 text-xs text-warn">Request: <?= esc($item['special_note']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dl class="num ml-auto max-w-xs space-y-1.5 border-t border-shell-line px-4 py-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Subtotal</dt><dd><?= rs_money($order['subtotal']) ?></dd></div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="flex justify-between gap-4 text-pista-deep">
                        <dt>Discount<?= $order['coupon_code'] ? ' (' . esc($order['coupon_code']) . ')' : '' ?></dt>
                        <dd>&minus;<?= rs_money($order['discount_total']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Delivery</dt><dd><?= rs_money($order['shipping_total']) ?></dd></div>
                <?php if ((float) $order['tax_total'] > 0): ?>
                    <div class="flex justify-between gap-4"><dt class="text-ink-muted">Tax</dt><dd><?= rs_money($order['tax_total']) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between gap-4 border-t border-shell-line pt-1.5 font-semibold">
                    <dt>Total</dt><dd><?= rs_money($order['grand_total']) ?></dd>
                </div>
            </dl>
        </section>

        <!-- Dispatch -->
        <?php if ($canManage): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Dispatch</h2>

                <?php if ($shipment !== null): ?>
                    <dl class="num mt-3 space-y-1 text-sm">
                        <div class="flex gap-3"><dt class="w-28 text-ink-muted">Courier</dt><dd><?= esc($shipment['courier_name']) ?></dd></div>
                        <div class="flex gap-3"><dt class="w-28 text-ink-muted">Tracking</dt><dd><?= esc($shipment['tracking_number']) ?></dd></div>
                        <div class="flex gap-3"><dt class="w-28 text-ink-muted">Sent</dt>
                            <dd><?= esc(date('j M Y, H:i', strtotime((string) $shipment['dispatched_at']))) ?></dd></div>
                    </dl>
                <?php endif; ?>

                <form method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/dispatch') ?>"
                      class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?= csrf_field() ?>
                    <label>
                        <span class="rs-label">Courier</span>
                        <input type="text" name="courier_name" class="rs-input" required maxlength="120" placeholder="Delhivery">
                    </label>
                    <label>
                        <span class="rs-label">Tracking number</span>
                        <input type="text" name="tracking_number" class="rs-input" required maxlength="120">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Tracking link <span class="text-ink-muted">(optional)</span></span>
                        <input type="url" name="tracking_url" class="rs-input" maxlength="255" placeholder="https://">
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">
                            Record dispatch<?= in_array('dispatched', $nextStates, true) ? ' &amp; mark dispatched' : '' ?>
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- History -->
        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                History
            </h2>
            <ul class="divide-y divide-shell-line text-sm">
                <?php foreach ($history as $entry): ?>
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-2.5">
                        <span>
                            <?php if ($entry['from_status'] !== null): ?>
                                <span class="text-ink-muted"><?= esc($entry['from_status']) ?> &rarr;</span>
                            <?php endif; ?>
                            <span class="font-medium"><?= esc($entry['to_status']) ?></span>
                            <?php if (! empty($entry['note'])): ?>
                                <span class="text-ink-muted">— <?= esc($entry['note']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="num font-mono text-[0.625rem] text-ink-muted">
                            <?= esc(date('j M, H:i', strtotime((string) $entry['created_at']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <!-- ------------------------------------------------------- sidebar -->
    <aside class="space-y-5">
        <?php if ($canManage): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Status</h2>
                <p class="mt-2"><span class="rs-badge rs-badge--brass"><?= esc($statuses[$order['status']] ?? $order['status']) ?></span></p>

                <?php if ($nextStates === []): ?>
                    <p class="rs-help mt-3">This order is closed. No further changes from here.</p>
                <?php else: ?>
                    <form method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/status') ?>" class="mt-4">
                        <?= csrf_field() ?>
                        <label class="block">
                            <span class="rs-label">Move to</span>
                            <select name="status" class="rs-select">
                                <?php foreach ($nextStates as $state): ?>
                                    <option value="<?= esc($state, 'attr') ?>"><?= esc($statuses[$state] ?? $state) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="mt-3 block">
                            <span class="rs-label">Note <span class="text-ink-muted">(optional)</span></span>
                            <input type="text" name="note" class="rs-input" maxlength="500">
                        </label>
                        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm mt-3 w-full">Update status</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Payment</h2>
                <p class="mt-2"><span class="rs-badge rs-badge--soft"><?= esc($payments[$order['payment_status']] ?? '') ?></span></p>
                <form method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/payment') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="rs-label">Mark as</span>
                        <select name="payment_status" class="rs-select">
                            <?php foreach ($payments as $key => $label): ?>
                                <option value="<?= esc($key, 'attr') ?>" <?= $order['payment_status'] === $key ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">Method <span class="text-ink-muted">(optional)</span></span>
                        <input type="text" name="payment_method" class="rs-input" maxlength="40"
                               placeholder="UPI, bank transfer" value="<?= esc($order['payment_method'] ?? '', 'attr') ?>">
                    </label>
                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm mt-3 w-full">Record payment</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="border border-shell-line bg-white p-4">
            <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Customer</h2>
            <dl class="mt-3 space-y-1 text-sm">
                <div><dt class="sr-only">Name</dt><dd class="font-medium"><?= esc($order['customer_name']) ?></dd></div>
                <div><dt class="sr-only">Email</dt>
                    <dd><a href="mailto:<?= esc($order['customer_email'], 'attr') ?>" class="rs-link"><?= esc($order['customer_email']) ?></a></dd></div>
                <div><dt class="sr-only">Phone</dt><dd class="num"><?= esc($order['customer_phone']) ?></dd></div>
            </dl>

            <?php if (! empty($order['ship_line1'])): ?>
                <h3 class="mt-4 font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">Deliver to</h3>
                <address class="mt-1.5 text-sm leading-relaxed not-italic">
                    <?= esc($order['ship_name']) ?><br>
                    <?= esc($order['ship_line1']) ?><br>
                    <?php if (! empty($order['ship_line2'])): ?><?= esc($order['ship_line2']) ?><br><?php endif; ?>
                    <?= esc($order['ship_city']) ?>, <?= esc($order['ship_state']) ?>
                    <span class="num"><?= esc($order['ship_postal_code']) ?></span><br>
                    <span class="num"><?= esc($order['ship_phone']) ?></span>
                </address>
            <?php endif; ?>

            <?php if (! empty($order['bill_gstin'])): ?>
                <p class="num mt-3 text-xs text-ink-muted">GSTIN <?= esc($order['bill_gstin']) ?></p>
            <?php endif; ?>
        </section>

        <?php if (! empty($order['customer_note'])): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Customer note</h2>
                <p class="mt-2 text-sm"><?= esc($order['customer_note']) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Internal note</h2>
                <form method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/note') ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <textarea name="admin_note" class="rs-textarea" rows="3" maxlength="2000"><?= esc($order['admin_note'] ?? '') ?></textarea>
                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm mt-2 w-full">Save note</button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?= $this->endSection() ?>
