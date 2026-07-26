<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'People',
    'heading'    => 'Customers',
    'subheading' => $total . ' address' . ($total === 1 ? '' : 'es') . ', built from orders — guests included.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label class="min-w-52 flex-1">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input" placeholder="Name, email or phone"
                   value="<?= esc($q ?? '', 'attr') ?>">
        </label>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Search</button>
        <a href="<?= site_url('admin/customers') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($customers === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No customers match that.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Customer</th>
                        <th class="px-4 py-2.5">Contact</th>
                        <th class="num px-4 py-2.5 text-right">Orders</th>
                        <th class="num px-4 py-2.5 text-right">Enquiries</th>
                        <th class="num px-4 py-2.5 text-right">Spend</th>
                        <th class="px-4 py-2.5">Last seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($customers as $customer): ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <a href="<?= site_url('admin/customers/' . rawurlencode((string) $customer['email'])) ?>"
                                   class="rs-link font-medium"><?= esc($customer['name']) ?></a>
                                <?php if ($customer['customer_id'] !== null): ?>
                                    <span class="rs-badge rs-badge--soft ml-1">Account</span>
                                <?php else: ?>
                                    <span class="rs-badge rs-badge--soft ml-1">Guest</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-ink-muted">
                                <span class="block"><?= esc($customer['email']) ?></span>
                                <span class="num block text-xs"><?= esc($customer['phone']) ?></span>
                            </td>
                            <td class="num px-4 py-2.5 text-right"><?= (int) $customer['orders'] ?></td>
                            <td class="num px-4 py-2.5 text-right text-ink-muted"><?= (int) $customer['enquiries'] ?></td>
                            <td class="num px-4 py-2.5 text-right font-semibold"><?= rs_money($customer['spend']) ?></td>
                            <td class="num px-4 py-2.5 text-xs text-ink-muted">
                                <?= esc(date('j M y', strtotime((string) $customer['last_seen']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
                <nav class="flex items-center justify-between gap-4 border-t border-shell-line px-4 py-3 text-sm">
                    <?php $base = site_url('admin/customers') . '?' . http_build_query(array_filter(['q' => $q])); ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= esc($base . '&page=' . ($page - 1), 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Previous</a>
                    <?php else: ?>
                        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Previous</span>
                    <?php endif; ?>
                    <p class="num font-mono text-[0.625rem] tracking-widest text-ink-muted uppercase">
                        Page <?= $page ?> of <?= $pages ?>
                    </p>
                    <?php if ($page < $pages): ?>
                        <a href="<?= esc($base . '&page=' . ($page + 1), 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Next</a>
                    <?php else: ?>
                        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Next</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
