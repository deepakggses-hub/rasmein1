<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Your account</p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">Orders</h1>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="border border-shell-line bg-white">
        <?php if ($orders === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-muted">
                Nothing here yet. <a href="<?= site_url('shop') ?>" class="rs-link text-mulberry">Start browsing</a>.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($orders as $order): ?>
                    <li class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <a href="<?= site_url('account/orders/' . $order['uuid']) ?>"
                                   class="rs-link num font-semibold"><?= esc($order['order_ref']) ?></a>
                                <p class="num mt-1 text-xs text-ink-muted">
                                    <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                                    <?php if ($order['journey_mode'] === 'enquire_now'): ?>
                                        &middot; <span class="rs-badge rs-badge--enquire">Enquiry</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rs-badge rs-badge--soft"><?= esc($order['status']) ?></span>
                                <span class="num font-semibold"><?= rs_money($order['grand_total']) ?></span>
                                <a href="<?= site_url('account/orders/' . $order['uuid']) ?>"
                                   class="rs-btn rs-btn--outline rs-btn--sm">View</a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= view('partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
