<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Sales',
    'heading'    => 'Coupons',
    'subheading' => $total . ' code' . ($total === 1 ? '' : 's') . '. Values are recalculated at checkout, never stored on a cart.',
    'actions'    => '<a href="' . site_url('admin/coupons/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New coupon</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($coupons === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No coupons yet.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Code</th>
                        <th class="px-4 py-2.5">Discount</th>
                        <th class="num px-4 py-2.5 text-right">Minimum</th>
                        <th class="num px-4 py-2.5 text-right">Used</th>
                        <th class="px-4 py-2.5">Window</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($coupons as $coupon): ?>
                        <?php
                        $expired = $coupon['ends_at'] !== null && strtotime((string) $coupon['ends_at']) < time();
                        $spent   = $coupon['usage_limit_total'] !== null
                            && (int) $coupon['used_count'] >= (int) $coupon['usage_limit_total'];
                        ?>
                        <tr class="hover:bg-shell">
                            <td class="num px-4 py-2.5">
                                <a href="<?= site_url('admin/coupons/' . $coupon['id'] . '/edit') ?>"
                                   class="rs-link font-mono font-medium"><?= esc($coupon['code']) ?></a>
                                <?php if (! empty($coupon['description'])): ?>
                                    <span class="block text-xs text-ink-muted"><?= esc(rs_excerpt($coupon['description'], 40)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num px-4 py-2.5">
                                <?= match ($coupon['discount_type']) {
                                    'percent'       => (float) $coupon['value'] . '%'
                                        . ($coupon['max_discount'] !== null ? ' (max ' . rs_money($coupon['max_discount']) . ')' : ''),
                                    'fixed'         => rs_money($coupon['value']),
                                    'free_shipping' => 'Free delivery',
                                    default         => '—',
                                } ?>
                            </td>
                            <td class="num px-4 py-2.5 text-right text-ink-muted">
                                <?= (float) $coupon['min_order_value'] > 0 ? rs_money($coupon['min_order_value']) : '—' ?>
                            </td>
                            <td class="num px-4 py-2.5 text-right">
                                <?= (int) $coupon['used_count'] ?><?= $coupon['usage_limit_total'] !== null ? ' / ' . (int) $coupon['usage_limit_total'] : '' ?>
                            </td>
                            <td class="num px-4 py-2.5 text-xs text-ink-muted">
                                <?= $coupon['starts_at'] !== null ? esc(date('j M y', strtotime((string) $coupon['starts_at']))) : 'any time' ?>
                                &ndash;
                                <?= $coupon['ends_at'] !== null ? esc(date('j M y', strtotime((string) $coupon['ends_at']))) : 'open' ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <?php if (! $coupon['is_active']): ?>
                                    <span class="rs-badge rs-badge--out">Off</span>
                                <?php elseif ($expired): ?>
                                    <span class="rs-badge rs-badge--out">Expired</span>
                                <?php elseif ($spent): ?>
                                    <span class="rs-badge rs-badge--out">Used up</span>
                                <?php else: ?>
                                    <span class="rs-badge rs-badge--enquire">Live</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="<?= site_url('admin/coupons/' . $coupon['id'] . '/edit') ?>"
                                   class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= view('admin/partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
