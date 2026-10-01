<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * Track an order or enquiry without signing in.
 *
 * Two states in one view: the lookup form, and the result. The result body is
 * `partials/order_detail`, shared with the account page — a guest and a
 * signed-in customer must not be shown different totals for the same order.
 *
 * @var bool|null $found
 */
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Track</p>

        <?php if ($found): ?>
            <h1 class="num mt-4 text-3xl sm:text-4xl"><?= esc($order['order_ref']) ?></h1>
            <p class="mt-3 text-ink-muted">
                Placed <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
                &middot;
                <span class="rs-badge <?= $isEnquiry ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                    <?= esc($isEnquiry && $stage !== null
                        ? $stage['label']
                        : (config(\Config\Rasmein::class)->orderStatuses[$order['status']] ?? $order['status'])) ?>
                </span>
            </p>
        <?php else: ?>
            <h1 class="mt-4 text-4xl sm:text-[2.75rem]">Where is my order?</h1>
            <p class="mt-4 max-w-xl leading-relaxed text-ink-muted">
                No account needed. Enter the reference from your confirmation and the
                email address or phone number you gave us.
            </p>
        <?php endif; ?>
    </div>
</header>

<div class="rs-shell py-10 lg:py-14">
    <?php if ($found): ?>
        <div class="mx-auto max-w-3xl space-y-6">
            <?= view('partials/order_detail', [
                'order'      => $order,
                'items'      => $items,
                'components' => $components,
                'shipment'   => $shipment,
                'enquiry'    => $enquiry,
                'isEnquiry'  => $isEnquiry,
                'stage'      => $stage,
                'amount'     => $amount,
            ]) ?>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-muted">
                <a href="<?= site_url('track') ?>" class="rs-link text-mulberry font-medium">Look up another</a>
                <span aria-hidden="true">&middot;</span>
                <span>
                    Questions?
                    <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>?subject=<?= esc(rawurlencode($order['order_ref']), 'attr') ?>"
                       class="rs-link text-mulberry font-medium">Write to us</a>
                    quoting <?= esc($order['order_ref']) ?>.
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="mx-auto max-w-md">
            <form method="post" action="<?= site_url('track') ?>" class="grid gap-5 border border-shell-line bg-white p-6">
                <?= csrf_field() ?>

                <label>
                    <span class="rs-label">Order or enquiry reference</span>
                    <input type="text" name="reference" class="rs-input num" required maxlength="32"
                           placeholder="RSM-000123" autocomplete="off" autofocus
                           value="<?= esc(old('reference') ?? '', 'attr') ?>">
                    <span class="rs-help">It is at the top of your confirmation email.</span>
                </label>

                <label>
                    <span class="rs-label">Email or phone you gave us</span>
                    <input type="text" name="contact" class="rs-input" required maxlength="191"
                           autocomplete="email" value="<?= esc(old('contact') ?? '', 'attr') ?>">
                </label>

                <button type="submit" class="rs-btn rs-btn--primary w-full">Find my order</button>
            </form>

            <p class="rs-help mt-5 text-center">
                Have an account?
                <a href="<?= site_url('account/orders') ?>" class="rs-link text-mulberry font-medium">See all your orders</a>.
            </p>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
