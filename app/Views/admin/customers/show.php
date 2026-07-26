<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Customer',
    'heading'    => $email,
    'subheading' => count($orders) . ' record(s) · ' . rs_money($spend) . ' spent'
        . ($account !== null ? ' · has an account' : ' · guest checkout only'),
    'actions'    => '<a href="' . site_url('admin/customers') . '" class="rs-btn rs-btn--outline rs-btn--sm">All customers</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-shell-line bg-shell-deep text-left">
                <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                    <th class="px-4 py-2.5">Reference</th>
                    <th class="px-4 py-2.5">Date</th>
                    <th class="px-4 py-2.5">Kind</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="num px-4 py-2.5 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-shell-line">
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-shell">
                        <td class="num px-4 py-2.5">
                            <a href="<?= site_url('admin/orders/' . $order['id']) ?>" class="rs-link font-medium">
                                <?= esc($order['order_ref']) ?>
                            </a>
                        </td>
                        <td class="num px-4 py-2.5 text-ink-muted">
                            <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="rs-badge <?= $order['journey_mode'] === 'enquire_now' ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                                <?= $order['journey_mode'] === 'enquire_now' ? 'Enquiry' : 'Order' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5"><span class="rs-badge rs-badge--soft"><?= esc($order['status']) ?></span></td>
                        <td class="num px-4 py-2.5 text-right font-semibold"><?= rs_money($order['grand_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
