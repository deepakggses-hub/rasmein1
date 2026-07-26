<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Your account</p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">
            Hello, <?= esc(explode(' ', (string) $customer['name'])[0]) ?>.
        </h1>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="space-y-8">
        <ul class="grid gap-4 sm:grid-cols-3">
            <?php foreach ([
                ['Orders placed', (string) count($orders), 'account/orders'],
                ['Total spent', rs_money($spend), 'account/orders'],
                ['Saved for later', (string) $wishlist, 'wishlist'],
            ] as [$label, $value, $link]): ?>
                <li>
                    <a href="<?= site_url($link) ?>" class="rs-card block bg-white p-5">
                        <p class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase"><?= esc($label) ?></p>
                        <p class="num mt-2 font-display text-2xl font-semibold text-mulberry"><?= esc($value) ?></p>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <section>
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="rs-eyebrow">Recent orders</h2>
                <a href="<?= site_url('account/orders') ?>" class="rs-link text-sm text-ink-muted">See all</a>
            </div>
            <div class="mt-4 border border-shell-line bg-white">
                <?php if ($orders === []): ?>
                    <p class="px-4 py-8 text-sm text-ink-muted">
                        Nothing yet. <a href="<?= site_url('build') ?>" class="rs-link text-mulberry">Build a gift box</a>
                        or <a href="<?= site_url('shop') ?>" class="rs-link text-mulberry">browse the shop</a>.
                    </p>
                <?php else: ?>
                    <ul class="divide-y divide-shell-line text-sm">
                        <?php foreach ($orders as $order): ?>
                            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <span>
                                    <a href="<?= site_url('account/orders/' . $order['uuid']) ?>" class="rs-link num font-medium">
                                        <?= esc($order['order_ref']) ?>
                                    </a>
                                    <span class="num ml-3 text-xs text-ink-muted">
                                        <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                                    </span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span class="rs-badge rs-badge--soft"><?= esc($order['status']) ?></span>
                                    <span class="num font-semibold"><?= rs_money($order['grand_total']) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Your details</h2>
                <form method="post" action="<?= site_url('account/details') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="rs-label">Name</span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               value="<?= esc($customer['name'], 'attr') ?>">
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Email</span>
                        <input type="email" class="rs-input" value="<?= esc($customer['email'], 'attr') ?>" disabled>
                        <span class="rs-help">Write to us if you need this changed — we verify the new address.</span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Phone</span>
                        <input type="tel" name="phone" class="rs-input" maxlength="20"
                               value="<?= esc($customer['phone'] ?? '', 'attr') ?>">
                    </label>
                    <label class="mt-4 flex items-start gap-2.5 text-sm">
                        <input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 accent-mulberry"
                               <?= $customer['marketing_opt_in'] ? 'checked' : '' ?>>
                        <span>Email me occasionally about new boxes.</span>
                    </label>
                    <button type="submit" class="rs-btn rs-btn--outline mt-5 w-full">Save details</button>
                </form>
            </section>

            <section class="border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Password</h2>
                <form method="post" action="<?= site_url('account/password') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="rs-label">Current password</span>
                        <input type="password" name="current_password" class="rs-input" required
                               autocomplete="current-password">
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">New password</span>
                        <input type="password" name="new_password" class="rs-input" required minlength="10"
                               autocomplete="new-password">
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Type it again</span>
                        <input type="password" name="confirm_password" class="rs-input" required
                               autocomplete="new-password">
                    </label>
                    <button type="submit" class="rs-btn rs-btn--outline mt-5 w-full">Change password</button>
                </form>
            </section>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
