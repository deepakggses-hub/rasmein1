<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The account home.
 *
 * WHAT CHANGED AND WHY
 *
 * It carried a "Password" panel — current password, new password, confirm —
 * posting to `account/password`. That route does not exist and never did after
 * sign-in went passwordless: the form 404'd, and it asked for a password the
 * customer has never had. It is gone, along with the unreachable controller
 * action behind it. A broken control is worse than a missing one.
 *
 * In its place, the things this page is actually for: what is in flight, how
 * to get back to it, and how signing in works now.
 *
 * @var array<string, mixed> $customer
 * @var array<int, array<string, mixed>> $orders
 */
$config = config(\Config\Rasmein::class);

// Enquiries and purchases are both `orders` rows. They read very differently
// to a customer, so they are counted and labelled separately rather than
// lumped into one "orders placed" figure that includes things never bought.
$enquiryCount = 0;

foreach ($orders as $row) {
    if (($row['journey_mode'] ?? '') === 'enquire_now') { $enquiryCount++; }
}
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Your account</p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">
            Hello, <?= esc(explode(' ', (string) $customer['name'])[0]) ?>.
        </h1>
        <p class="mt-3 text-ink-muted">
            <?= esc($customer['email']) ?>
        </p>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="space-y-9">

        <?php /* ------------------------------------------------- at a glance
                 Three figures, each a link to the thing it counts. A number
                 nobody can act on is decoration. */ ?>
        <ul class="grid gap-3 sm:grid-cols-3">
            <?php foreach ([
                ['Orders', (string) count($orders), 'account/orders', 'bag'],
                ['Total spent', rs_money($spend), 'account/orders', 'reports'],
                ['Saved for later', (string) $wishlist, 'wishlist', 'heart'],
            ] as [$label, $value, $link, $icon]): ?>
                <li>
                    <a href="<?= site_url($link) ?>" class="rs-statcard">
                        <span class="rs-statcard__icon" aria-hidden="true"><?= rs_icon($icon, 'rs-statcard__svg') ?></span>
                        <span class="rs-statcard__label"><?= esc($label) ?></span>
                        <span class="num rs-statcard__value"><?= esc($value) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php /* --------------------------------------------- recent activity */ ?>
        <section>
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="rs-eyebrow">Recent</h2>
                <?php if ($orders !== []): ?>
                    <a href="<?= site_url('account/orders') ?>" class="rs-link text-sm text-ink-muted">See all</a>
                <?php endif; ?>
            </div>

            <?php if ($orders === []): ?>
                <div class="mt-4 border border-dashed border-shell-line bg-white px-6 py-10 text-center">
                    <p class="font-display text-xl">Nothing here yet.</p>
                    <p class="mt-2 text-sm text-ink-muted">
                        When you order or send an enquiry it will appear here, with its progress.
                    </p>
                    <div class="mt-5 flex flex-wrap justify-center gap-3">
                        <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--primary rs-btn--sm">Build a gift box</a>
                        <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Browse the shop</a>
                    </div>
                </div>
            <?php else: ?>
                <ul class="mt-4 grid gap-3">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $isEnq = ($order['journey_mode'] ?? '') === 'enquire_now';
                        // An enquiry's `status` stays "pending" for the whole
                        // conversation, so it is the wrong thing to show. The
                        // pipeline stage is loaded per row below.
                        $label = $isEnq
                            ? ($config->enquiryStagesPublic[$order['lead_status'] ?? 'new'] ?? 'Received')
                            : ($config->orderStatuses[$order['status']] ?? $order['status']);
                        ?>
                        <li>
                            <a href="<?= site_url('account/orders/' . $order['uuid']) ?>" class="rs-orderrow">
                                <span class="rs-orderrow__main">
                                    <span class="rs-orderrow__kind"><?= $isEnq ? 'Enquiry' : 'Order' ?></span>
                                    <span class="num rs-orderrow__ref"><?= esc($order['order_ref']) ?></span>
                                    <span class="num rs-orderrow__date">
                                        <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                                    </span>
                                </span>
                                <span class="rs-orderrow__side">
                                    <span class="rs-badge <?= $isEnq ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                                        <?= esc($label) ?>
                                    </span>
                                    <span class="num rs-orderrow__total"><?= rs_money($order['grand_total']) ?></span>
                                    <?= rs_icon('arrow-right', 'rs-orderrow__go') ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <?php /* ----------------------------------------------- details */ ?>
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
                        <span class="rs-help">Changing this needs the new address verified — write to us.</span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Phone</span>
                        <input type="tel" name="phone" class="rs-input num" maxlength="20"
                               value="<?= esc($customer['phone'] ?? '', 'attr') ?>">
                    </label>
                    <label class="mt-4 flex items-start gap-2.5 text-sm">
                        <input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 accent-mulberry"
                               <?= $customer['marketing_opt_in'] ? 'checked' : '' ?>>
                        <span>Email me occasionally about new boxes.</span>
                    </label>
                    <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">Save details</button>
                </form>
            </section>

            <div class="space-y-6">
                <?php /* ------------------------------------- how you sign in
                         This replaced the password form. There is no password
                         to change, and saying so is more useful than a control
                         that cannot work. */ ?>
                <section class="border border-shell-line bg-white p-6">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Signing in</h2>
                    <p class="mt-3 text-sm leading-relaxed text-ink-soft">
                        There is no password on this account. When you sign in we email a
                        six-digit code that lasts ten minutes — nothing to remember, and
                        nothing that can be reused if it is seen.
                    </p>
                    <?php if (! empty($customer['google_id'])): ?>
                        <p class="mt-3 flex items-center gap-2 text-sm text-ink-muted">
                            <?= rs_icon('google', 'rs-inlineicon') ?>
                            Google is connected to this account.
                        </p>
                    <?php endif; ?>
                </section>

                <?php /* ------------------------------------------ addresses */ ?>
                <section class="border border-shell-line bg-white p-6">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Addresses</h2>
                    <p class="mt-3 text-sm leading-relaxed text-ink-soft">
                        <?php if ($addresses === 0): ?>
                            None saved yet. Adding one fills your details in at checkout.
                        <?php else: ?>
                            <span class="num font-semibold text-ink"><?= (int) $addresses ?></span>
                            saved — used to fill in checkout for you.
                        <?php endif; ?>
                    </p>
                    <a href="<?= site_url('account/addresses') ?>" class="rs-btn rs-btn--outline rs-btn--sm mt-4">
                        <?= $addresses === 0 ? 'Add an address' : 'Manage addresses' ?>
                    </a>
                </section>
            </div>
        </div>

        <p class="rs-help">
            Ordered without signing in? You can still
            <a href="<?= site_url('track') ?>" class="rs-link text-mulberry font-medium">track it here</a>
            with the reference and your email.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
