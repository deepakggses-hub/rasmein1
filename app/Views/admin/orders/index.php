<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Sales',
    'heading'    => 'Orders',
    'subheading' => $total . ' order' . ($total === 1 ? '' : 's') . ' matching.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label class="min-w-52 flex-1">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input" placeholder="Reference, name, email or phone"
                   value="<?= esc($filters['q'] ?? '', 'attr') ?>">
        </label>
        <label>
            <span class="rs-label">Status</span>
            <select name="status" class="rs-select w-auto">
                <option value="">Any</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="rs-label">Payment</span>
            <select name="payment" class="rs-select w-auto">
                <option value="">Any</option>
                <?php foreach ($payments as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>" <?= $filters['payment'] === $key ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Filter</button>
        <a href="<?= site_url('admin/orders') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($orders === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No orders match that. Try clearing the filters.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Reference</th>
                        <th class="px-4 py-2.5">Placed</th>
                        <th class="px-4 py-2.5">Customer</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Payment</th>
                        <th class="num px-4 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-shell">
                            <td class="num px-4 py-3">
                                <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="rs-link font-medium">
                                    <?= esc($order['order_ref']) ?>
                                </a>
                            </td>
                            <td class="num px-4 py-3 text-ink-muted">
                                <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block"><?= esc(rs_excerpt($order['customer_name'], 22)) ?></span>
                                <span class="block text-xs text-ink-muted"><?= esc($order['customer_phone']) ?></span>
                            </td>
                            <td class="px-4 py-3"><span class="rs-badge rs-badge--soft"><?= esc($statuses[$order['status']] ?? $order['status']) ?></span></td>
                            <td class="px-4 py-3">
                                <span class="rs-badge <?= $order['payment_status'] === 'paid' ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                                    <?= esc($payments[$order['payment_status']] ?? $order['payment_status']) ?>
                                </span>
                            </td>
                            <td class="num px-4 py-3 text-right font-semibold"><?= rs_money($order['grand_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= view('admin/partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
