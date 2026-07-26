<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$peak = 0.0;
foreach ($daily as $d) { $peak = max($peak, (float) $d['revenue']); }
$qs = '?days=' . (int) $days;
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Insight',
    'heading'    => 'Reports',
    'subheading' => 'Cancelled and refunded orders are excluded from every figure.',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">

    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label>
            <span class="rs-label">Period</span>
            <select name="days" class="rs-select w-auto" data-auto-submit>
                <?php foreach ($ranges as $k => $label): ?>
                    <option value="<?= $k ?>" <?= (int) $k === (int) $days ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Go</button></noscript>
        </label>
        <div class="ml-auto flex flex-wrap gap-2">
            <?php foreach (['orders' => 'Orders', 'enquiries' => 'Enquiries', 'products' => 'Products', 'customers' => 'Customers'] as $k => $label): ?>
                <a href="<?= site_url('admin/reports/export/' . $k) . $qs ?>" class="rs-btn rs-btn--outline rs-btn--sm">
                    <?= esc($label) ?> CSV
                </a>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $cards = [
            ['Revenue', rs_money($summary['revenue']), $summary['orders'] . ' order' . ($summary['orders'] === 1 ? '' : 's')],
            ['Average order', rs_money($summary['average']), 'across the period'],
            ['Discounts given', rs_money($summary['discounts']), 'from coupons'],
            ['Enquiries', (string) $summary['enquiries'], $summary['won'] . ' won · ' . $summary['winRate'] . '% conversion'],
        ];
        foreach ($cards as [$label, $value, $sub]):
        ?>
            <div class="border border-shell-line bg-white p-5">
                <p class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase"><?= esc($label) ?></p>
                <p class="num mt-2 font-display text-2xl font-semibold text-mulberry"><?= esc($value) ?></p>
                <p class="rs-help mt-1"><?= esc($sub) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Revenue by day. A bar per day, drawn in CSS — no chart library for
         something this simple, and it prints correctly. -->
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Revenue by day</h2>
        <?php if ($daily === []): ?>
            <p class="mt-4 text-sm text-ink-muted">Nothing in this period.</p>
        <?php else: ?>
            <ul class="mt-5 flex items-end gap-1 overflow-x-auto" style="height:9rem">
                <?php foreach ($daily as $d): ?>
                    <?php $pct = $peak > 0 ? max(2, (int) round((float) $d['revenue'] / $peak * 100)) : 2; ?>
                    <li class="flex min-w-3 flex-1 flex-col justify-end"
                        title="<?= esc(date('j M', strtotime((string) $d['day']))) ?> — <?= rs_money($d['revenue']) ?> from <?= (int) $d['orders'] ?> order(s)">
                        <span class="block w-full bg-mulberry" style="height:<?= $pct ?>%"></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="num mt-2 flex justify-between font-mono text-[0.5625rem] text-ink-muted">
                <span><?= esc(date('j M', strtotime((string) $daily[0]['day']))) ?></span>
                <span>peak <?= rs_money($peak) ?></span>
                <span><?= esc(date('j M', strtotime((string) end($daily)['day']))) ?></span>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <?php
        $tables = [
            ['Best sellers', $topProducts, ['name' => 'Product', 'units' => 'Units', 'revenue' => 'Revenue']],
            ['By category', $byCategory, ['category' => 'Category', 'units' => 'Units', 'revenue' => 'Revenue']],
        ];
        foreach ($tables as [$title, $rows, $columns]):
        ?>
            <section>
                <h2 class="rs-eyebrow"><?= esc($title) ?></h2>
                <div class="mt-4 overflow-x-auto border border-shell-line bg-white">
                    <?php if ($rows === []): ?>
                        <p class="px-4 py-6 text-sm text-ink-muted">Nothing yet.</p>
                    <?php else: ?>
                        <table class="w-full text-sm">
                            <thead class="border-b border-shell-line bg-shell-deep text-left">
                                <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                                    <?php foreach ($columns as $key => $label): ?>
                                        <th class="px-4 py-2.5 <?= $key === 'name' || $key === 'category' ? '' : 'num text-right' ?>">
                                            <?= esc($label) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-shell-line">
                                <?php foreach ($rows as $row): ?>
                                    <tr class="hover:bg-shell">
                                        <?php foreach ($columns as $key => $label): ?>
                                            <td class="px-4 py-2 <?= $key === 'name' || $key === 'category' ? '' : 'num text-right' ?>">
                                                <?= $key === 'revenue'
                                                    ? rs_money($row[$key])
                                                    : esc(rs_excerpt((string) $row[$key], 34)) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section>
            <h2 class="rs-eyebrow">Enquiry pipeline</h2>
            <div class="mt-4 border border-shell-line bg-white">
                <?php if ($pipeline === []): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">No enquiries in this period.</p>
                <?php else: ?>
                    <ul class="divide-y divide-shell-line text-sm">
                        <?php foreach ($pipeline as $stage): ?>
                            <li class="num flex items-center justify-between gap-4 px-4 py-2.5">
                                <span class="rs-badge rs-badge--soft"><?= esc($stage['lead_status']) ?></span>
                                <span>
                                    <span class="font-semibold"><?= (int) $stage['count'] ?></span>
                                    <span class="ml-3 text-ink-muted"><?= rs_money($stage['value']) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h2 class="rs-eyebrow">Coupon use</h2>
            <div class="mt-4 border border-shell-line bg-white">
                <?php if ($coupons === []): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">No coupons redeemed in this period.</p>
                <?php else: ?>
                    <ul class="divide-y divide-shell-line text-sm">
                        <?php foreach ($coupons as $coupon): ?>
                            <li class="num flex items-center justify-between gap-4 px-4 py-2.5">
                                <span class="font-mono"><?= esc($coupon['code']) ?></span>
                                <span>
                                    <span class="font-semibold"><?= (int) $coupon['uses'] ?></span>
                                    <span class="ml-3 text-ink-muted">&minus;<?= rs_money($coupon['given']) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
