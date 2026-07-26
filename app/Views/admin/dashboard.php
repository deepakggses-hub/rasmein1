<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array<string, mixed> $today
 * @var array<string, int>   $needsWork
 * @var array<int, \App\Entities\Product> $lowStock
 */
?>

<?= view('admin/partials/header', [
    'eyebrow' => 'Overview',
    'heading' => 'Good to see you, ' . esc(explode(' ', (string) ($admin['name'] ?? 'there'))[0]) . '.',
    'subheading' => 'Where things stand right now.',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">

    <!-- Needs attention first: what a person has to DO, before the numbers. -->
    <?php
    $queue = [
        ['label' => 'Orders to confirm', 'count' => $needsWork['pending_orders'], 'url' => 'admin/orders?status=pending'],
        ['label' => 'Awaiting payment',  'count' => $needsWork['unpaid_orders'],  'url' => 'admin/orders?payment=unpaid'],
        ['label' => 'Ready to dispatch', 'count' => $needsWork['to_dispatch'],    'url' => 'admin/orders?status=packed'],
        ['label' => 'New enquiries',     'count' => $needsWork['new_enquiries'],  'url' => 'admin/enquiries?status=new'],
        ['label' => 'Follow-ups overdue','count' => $needsWork['overdue_followup'],'url' => 'admin/enquiries?overdue=1'],
        ['label' => 'Unread notifications','count' => $needsWork['unread_notices'], 'url' => 'admin/notifications?show=unread'],
    ];
    $anything = array_sum(array_column($queue, 'count')) > 0;
    ?>
    <section>
        <h2 class="rs-eyebrow">Needs you</h2>
        <?php if (! $anything): ?>
            <p class="mt-4 border border-shell-line bg-white px-4 py-6 text-sm text-ink-muted">
                Nothing waiting. Everything is confirmed, paid and dispatched.
            </p>
        <?php else: ?>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <?php foreach ($queue as $entry): ?>
                    <li>
                        <a href="<?= site_url($entry['url']) ?>"
                           class="rs-card block bg-white p-4 <?= $entry['count'] > 0 ? 'border-brass' : '' ?>">
                            <p class="num font-display text-3xl font-semibold <?= $entry['count'] > 0 ? 'text-mulberry' : 'text-ink-muted' ?>">
                                <?= (int) $entry['count'] ?>
                            </p>
                            <p class="mt-1 text-sm text-ink-muted"><?= esc($entry['label']) ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Numbers -->
    <section>
        <h2 class="rs-eyebrow">Trade</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <?php foreach ([$today, $week, $month] as $window): ?>
                <div class="border border-shell-line bg-white p-5">
                    <p class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                        <?= esc($window['label']) ?>
                    </p>
                    <p class="num mt-2 font-display text-2xl font-semibold text-mulberry">
                        <?= rs_money($window['revenue']) ?>
                    </p>
                    <dl class="num mt-4 space-y-1 border-t border-shell-line pt-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Orders</dt>
                            <dd class="font-medium"><?= (int) $window['orders'] ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Enquiries</dt>
                            <dd class="font-medium"><?= (int) $window['enquiries'] ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Average order</dt>
                            <dd class="font-medium"><?= rs_money($window['average']) ?></dd>
                        </div>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="rs-help mt-2">Revenue excludes cancelled and refunded orders.</p>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <!-- Recent -->
        <section>
            <h2 class="rs-eyebrow">Latest in</h2>
            <div class="mt-4 overflow-x-auto border border-shell-line bg-white">
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
                                        <a href="<?= site_url(($row['journey_mode'] === 'enquire_now' ? 'admin/enquiries' : 'admin/orders/' . $row['id'])) ?>"
                                           class="rs-link font-medium"><?= esc($row['order_ref']) ?></a>
                                        <?php if ($row['journey_mode'] === 'enquire_now'): ?>
                                            <span class="rs-badge rs-badge--enquire ml-1">Enq</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5"><?= esc(rs_excerpt($row['customer_name'], 20)) ?></td>
                                    <td class="px-4 py-2.5">
                                        <span class="rs-badge rs-badge--soft"><?= esc($row['status']) ?></span>
                                    </td>
                                    <td class="num px-4 py-2.5 text-right font-medium"><?= rs_money($row['grand_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Low stock -->
        <section>
            <h2 class="rs-eyebrow">Running low</h2>
            <div class="mt-4 border border-shell-line bg-white">
                <?php if ($lowStock === []): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">Everything is comfortably in stock.</p>
                <?php else: ?>
                    <ul class="divide-y divide-shell-line">
                        <?php foreach ($lowStock as $product): ?>
                            <li class="num flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                                <span class="min-w-0"><?= esc(rs_excerpt($product->name, 30)) ?></span>
                                <span class="shrink-0 font-semibold <?= $product->stock_qty === 0 ? 'text-bad' : 'text-warn' ?>">
                                    <?= (int) $product->stock_qty ?> left
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if ($audit !== []): ?>
        <section>
            <h2 class="rs-eyebrow">Recent activity</h2>
            <ul class="mt-4 divide-y divide-shell-line border border-shell-line bg-white text-sm">
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
