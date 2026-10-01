<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * One order or enquiry, for the customer who placed it.
 *
 * The body is `partials/order_detail`, shared with the public tracking page so
 * the two cannot drift apart on stage wording or on which total to show.
 *
 * @var array<string, mixed> $order
 * @var array $amount
 */
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6"><?= $isEnquiry ? 'Enquiry' : 'Order' ?></p>
        <h1 class="num mt-4 text-3xl sm:text-4xl"><?= esc($order['order_ref']) ?></h1>
        <p class="mt-3 text-ink-muted">
            Placed <?= esc(date('j M Y', strtotime((string) $order['placed_at']))) ?>
            <?php /* An enquiry's state is the pipeline stage, in the customer's
                     words — `orders.status` stays "pending" for the whole
                     conversation and says nothing useful here. */ ?>
            &middot;
            <span class="rs-badge <?= $isEnquiry ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                <?= esc($isEnquiry && $stage !== null
                    ? $stage['label']
                    : (config(\Config\Rasmein::class)->orderStatuses[$order['status']] ?? $order['status'])) ?>
            </span>
        </p>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="space-y-6">
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

        <p class="text-sm text-ink-muted">
            Something not right? <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>?subject=<?= esc(rawurlencode($order['order_ref']), 'attr') ?>"
               class="rs-link text-mulberry font-medium">Write to us</a> quoting <?= esc($order['order_ref']) ?>.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
