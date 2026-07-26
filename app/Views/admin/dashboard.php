<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * The dashboard.
 *
 * Ordered by what a person opening it at 9am needs, not by what is easiest to
 * query: first what requires action, then how trade is going, then the state of
 * the shop, then history. Anything with a number that should be zero is styled
 * so a glance is enough.
 */
$peak = 0.0;
foreach ($trend as $day) { $peak = max($peak, (float) $day['revenue']); }
$firstName = esc(explode(' ', (string) ($admin['name'] ?? 'there'))[0]);
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Overview',
    'heading'    => 'Good to see you, ' . $firstName . '.',
    'subheading' => 'Where the shop stands, ' . date('l j F') . '.',
]) ?>

<div class="space-y-9 px-5 py-6 lg:px-8">

    <!-- ============================ needs you ============================ -->
    <?php
    $queue = [
        ['Orders to confirm',    $needsWork['pending_orders'],    'admin/orders?status=pending'],
        ['Awaiting payment',     $needsWork['unpaid_orders'],     'admin/orders?payment=unpaid'],
        ['Ready to dispatch',    $needsWork['to_dispatch'],       'admin/orders?status=packed'],
        ['New enquiries',        $needsWork['new_enquiries'],     'admin/enquiries?status=new'],
        ['Follow-ups overdue',   $needsWork['overdue_followup'],  'admin/enquiries?overdue=1'],
        ['Unread notifications', $needsWork['unread_notices'],    'admin/notifications?show=unread'],
    ];
    $waiting = array_sum(array_map(static fn (array $q): int => (int) $q[1], $queue));
    ?>
    <section>
        <div class="rs-section-head">
            <h2 class="rs-eyebrow">Needs you</h2>
            <?php if ($waiting > 0): ?>
                <span class="num rs-badge rs-badge--brass shrink-0"><?= $waiting ?></span>
            <?php endif; ?>
        </div>

        <?php if ($waiting === 0): ?>
            <p class="border border-shell-line bg-white px-5 py-6 text-sm text-ink-muted">
                Nothing waiting. Everything is confirmed, paid, packed and read.
            </p>
        <?php else: ?>
            <ul class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <?php foreach ($queue as [$label, $count, $url]): ?>
                    <li>
                        <a href="<?= site_url($url) ?>" class="rs-stat block hover:border-mulberry <?= $count > 0 ? 'rs-stat--alert' : '' ?>">
                            <span class="rs-stat__label"><?= esc($label) ?></span>
                            <span class="rs-stat__value num <?= $count === 0 ? 'text-ink-muted' : '' ?>"><?= (int) $count ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ============================== trade ============================== -->
    <section>
        <div class="rs-section-head"><h2 class="rs-eyebrow">Trade</h2></div>

        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_1.4fr]">
            <?php foreach ([$today, $week, $month] as $window): ?>
                <div class="rs-stat">
                    <span class="rs-stat__label"><?= esc($window['label']) ?></span>
                    <span class="rs-stat__value num"><?= rs_money($window['revenue']) ?></span>
                    <dl class="num mt-3 space-y-1 border-t border-shell-line pt-2.5 text-xs">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Orders</dt><dd class="font-semibold"><?= (int) $window['orders'] ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Enquiries</dt><dd class="font-semibold"><?= (int) $window['enquiries'] ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Average</dt><dd class="font-semibold"><?= rs_money($window['average']) ?></dd>
                        </div>
                    </dl>
                </div>
            <?php endforeach; ?>

            <div class="rs-stat">
                <span class="rs-stat__label">Last 14 days</span>
                <div class="rs-spark mt-3">
                    <?php foreach ($trend as $day): ?>
                        <?php
                        $height = $peak > 0 ? max(3, (int) round((float) $day['revenue'] / $peak * 100)) : 3;
                        $empty  = (float) $day['revenue'] <= 0;
                        ?>
                        <span class="rs-spark__bar <?= $empty ? 'rs-spark__bar--empty' : '' ?>"
                              style="height:<?= $height ?>%"
                              title="<?= esc(date('j M', strtotime((string) $day['day']))) ?> — <?= rs_money($day['revenue']) ?>, <?= (int) $day['orders'] ?> order(s)"></span>
                    <?php endforeach; ?>
                </div>
                <div class="num mt-2 flex justify-between font-mono text-[0.5625rem] text-ink-muted">
                    <span><?= esc(date('j M', strtotime((string) $trend[0]['day']))) ?></span>
                    <span>peak <?= rs_money($peak) ?></span>
                    <span>today</span>
                </div>
            </div>
        </div>
        <p class="rs-help mt-2">Cancelled and refunded orders are excluded from every figure.</p>
    </section>

    <!-- ============================ the shop ============================= -->
    <section>
        <div class="rs-section-head"><h2 class="rs-eyebrow">The shop</h2></div>
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $shop = [
                ['Products live', $catalogue['live'], $catalogue['hidden'] . ' hidden · ' . $catalogue['categories'] . ' categories', 'admin/products', false],
                ['Out of stock', $catalogue['out'], $catalogue['low'] . ' more running low', 'admin/products?state=low', $catalogue['out'] > 0],
                ['Without a photograph', $catalogue['noImage'], 'a product with no image sells poorly', 'admin/products', $catalogue['noImage'] > 0],
                ['Gift boxes live', $catalogue['giftBoxes'], 'configurable trays', 'admin/gift-boxes', false],
            ];
            foreach ($shop as [$label, $value, $note, $url, $bad]):
            ?>
                <li>
                    <a href="<?= site_url($url) ?>" class="rs-stat block hover:border-mulberry <?= $bad ? 'rs-stat--bad' : '' ?>">
                        <span class="rs-stat__label"><?= esc($label) ?></span>
                        <span class="rs-stat__value num"><?= (int) $value ?></span>
                        <span class="rs-stat__note"><?= esc($note) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- ============================== people ============================= -->
    <section>
        <div class="rs-section-head"><h2 class="rs-eyebrow">People</h2></div>
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $folk = [
                ['Customers who bought', (string) $people['buyers'], 'guests included', 'admin/customers'],
                ['Bought more than once', $people['repeat'] . ' · ' . $people['repeatRate'] . '%', 'repeat rate', 'admin/customers'],
                ['Accounts', (string) $people['accounts'], $people['newAccounts'] . ' new this month', 'admin/customers'],
                ['Staff who can sign in', (string) $people['staff'], 'active admin accounts', 'admin/staff'],
            ];
            foreach ($folk as [$label, $value, $note, $url]):
            ?>
                <li>
                    <a href="<?= site_url($url) ?>" class="rs-stat block hover:border-mulberry">
                        <span class="rs-stat__label"><?= esc($label) ?></span>
                        <span class="rs-stat__value num"><?= esc($value) ?></span>
                        <span class="rs-stat__note"><?= esc($note) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- ================== pipeline · baskets · mail ====================== -->
    <div class="grid gap-6 xl:grid-cols-3">
        <section>
            <div class="rs-section-head"><h2 class="rs-eyebrow">Enquiry pipeline</h2></div>
            <div class="border border-shell-line bg-white">
                <?php if ($pipeline['total'] === 0): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">No enquiries yet.</p>
                <?php else: ?>
                    <div class="border-b border-shell-line px-4 py-3">
                        <p class="num font-display text-xl font-semibold text-mulberry"><?= rs_money($pipeline['open']) ?></p>
                        <p class="rs-help">open pipeline value · <?= $pipeline['winRate'] ?>% won</p>
                    </div>
                    <ul class="divide-y divide-shell-line text-sm">
                        <?php foreach ($pipeline['stages'] as $stage => $data): ?>
                            <li class="num flex items-center justify-between gap-3 px-4 py-2">
                                <a href="<?= site_url('admin/enquiries?status=' . urlencode($stage)) ?>" class="rs-link">
                                    <span class="rs-badge rs-badge--soft"><?= esc($stage) ?></span>
                                </a>
                                <span>
                                    <span class="font-semibold"><?= (int) $data['count'] ?></span>
                                    <span class="ml-2 text-xs text-ink-muted"><?= rs_money($data['value']) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <div class="rs-section-head"><h2 class="rs-eyebrow">Baskets</h2></div>
            <div class="border border-shell-line bg-white">
                <ul class="divide-y divide-shell-line text-sm">
                    <?php foreach ([
                        'Being filled now' => [$baskets['active'], 'active in the last two hours'],
                        'Gone quiet'       => [$baskets['idle'], 'untouched for over two hours'],
                        'Abandoned'        => [$baskets['abandoned'], 'a week without activity'],
                    ] as $label => [$count, $note]): ?>
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span>
                                <span class="block"><?= esc($label) ?></span>
                                <span class="block text-xs text-ink-muted"><?= esc($note) ?></span>
                            </span>
                            <span class="num font-display text-xl font-semibold"><?= (int) $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section>
            <div class="rs-section-head"><h2 class="rs-eyebrow">Mail</h2></div>
            <div class="border <?= $mail['configured'] ? 'border-shell-line' : 'border-bad' ?> bg-white">
                <?php if (! $mail['configured']): ?>
                    <div class="border-b border-shell-line bg-rose/25 px-4 py-3 text-sm">
                        <p class="font-semibold text-bad">Not configured.</p>
                        <p class="mt-1 text-xs text-ink-soft">
                            Nothing can be sent — no order confirmations, no enquiry alerts,
                            no password resets.
                        </p>
                        <a href="<?= site_url('admin/mail') ?>" class="rs-btn rs-btn--primary rs-btn--sm mt-2.5">Set mail up</a>
                    </div>
                <?php endif; ?>
                <ul class="divide-y divide-shell-line text-sm">
                    <?php foreach (['queued' => 'Waiting to send', 'sent' => 'Sent', 'failed' => 'Gave up'] as $key => $label): ?>
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span><?= esc($label) ?></span>
                            <span class="num font-display text-xl font-semibold <?= $key === 'failed' && $mail[$key] > 0 ? 'text-bad' : '' ?>">
                                <?= (int) $mail[$key] ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($mail['configured']): ?>
                    <p class="rs-help border-t border-shell-line px-4 py-2.5">
                        <a href="<?= site_url('admin/mail') ?>" class="rs-link">Mail settings</a>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ================== recent · best sellers · low stock ============== -->
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="xl:col-span-2">
            <div class="rs-section-head">
                <h2 class="rs-eyebrow">Latest in</h2>
                <a href="<?= site_url('admin/orders') ?>" class="rs-link shrink-0 text-sm text-ink-muted">All orders</a>
            </div>
            <div class="overflow-x-auto border border-shell-line bg-white">
                <?php if ($recent === []): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">Nothing yet.</p>
                <?php else: ?>
                    <table class="w-full text-sm">
                        <thead class="border-b border-shell-line bg-shell-deep text-left">
                            <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                                <th class="px-4 py-2.5">Reference</th>
                                <th class="px-4 py-2.5">Customer</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="num px-4 py-2.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-shell-line">
                            <?php foreach ($recent as $row): ?>
                                <tr class="hover:bg-shell">
                                    <td class="num px-4 py-2.5">
                                        <a href="<?= site_url($row['journey_mode'] === 'enquire_now' ? 'admin/enquiries' : 'admin/orders/' . $row['id']) ?>"
                                           class="rs-link font-medium"><?= esc($row['order_ref']) ?></a>
                                        <?php if ($row['journey_mode'] === 'enquire_now'): ?>
                                            <span class="rs-badge rs-badge--enquire ml-1">Enq</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5"><?= esc(rs_excerpt($row['customer_name'], 22)) ?></td>
                                    <td class="px-4 py-2.5"><span class="rs-badge rs-badge--soft"><?= esc($row['status']) ?></span></td>
                                    <td class="num px-4 py-2.5 text-right font-semibold"><?= rs_money($row['grand_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <div class="space-y-6">
            <section>
                <div class="rs-section-head"><h2 class="rs-eyebrow">Best sellers</h2></div>
                <div class="border border-shell-line bg-white">
                    <?php if ($topProducts === []): ?>
                        <p class="px-4 py-6 text-sm text-ink-muted">Nothing sold in the last 30 days.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-shell-line text-sm">
                            <?php foreach ($topProducts as $product): ?>
                                <li class="num flex items-center justify-between gap-3 px-4 py-2.5">
                                    <span class="min-w-0"><?= esc(rs_excerpt($product['name'], 24)) ?></span>
                                    <span class="shrink-0 font-semibold"><?= (int) $product['units'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="rs-help border-t border-shell-line px-4 py-2">Units, last 30 days.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section>
                <div class="rs-section-head"><h2 class="rs-eyebrow">Running low</h2></div>
                <div class="border border-shell-line bg-white">
                    <?php if ($lowStock === []): ?>
                        <p class="px-4 py-6 text-sm text-ink-muted">Everything is comfortably in stock.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-shell-line text-sm">
                            <?php foreach ($lowStock as $product): ?>
                                <li class="num flex items-center justify-between gap-3 px-4 py-2.5">
                                    <a href="<?= site_url('admin/products/' . $product->id . '/edit') ?>"
                                       class="rs-link min-w-0"><?= esc(rs_excerpt($product->name, 24)) ?></a>
                                    <span class="shrink-0 font-semibold <?= $product->stock_qty === 0 ? 'text-bad' : 'text-warn' ?>">
                                        <?= (int) $product->stock_qty ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <?php if ($audit !== []): ?>
        <section>
            <div class="rs-section-head">
                <h2 class="rs-eyebrow">Recent activity</h2>
                <a href="<?= site_url('admin/audit') ?>" class="rs-link shrink-0 text-sm text-ink-muted">Full log</a>
            </div>
            <ul class="divide-y divide-shell-line border border-shell-line bg-white text-sm">
                <?php foreach ($audit as $entry): ?>
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-2.5">
                        <span>
                            <span class="font-medium"><?= esc($entry['admin_name'] ?? 'System') ?></span>
                            <span class="text-ink-muted"><?= esc(str_replace('_', ' ', $entry['action'])) ?></span>
                            <?php if (! empty($entry['summary'])): ?>
                                <span class="text-ink-muted">— <?= esc(rs_excerpt($entry['summary'], 60)) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="num font-mono text-[0.625rem] text-ink-muted">
                            <?= esc(date('j M, H:i', strtotime((string) $entry['created_at']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
