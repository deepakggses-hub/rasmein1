<?php
/**
 * Storefront layout.
 *
 * @var array  $seo
 * @var object $brand
 * @var string $journeyMode
 * @var bool   $isEnquire
 */
?>
<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#401026">

    <title><?= esc($seo['title']) ?></title>
    <meta name="description" content="<?= esc($seo['description']) ?>">
    <?php if (! empty($seo['noindex'])): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <link rel="canonical" href="<?= esc($seo['canonical'], 'attr') ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= esc($brand->brandName) ?>">
    <meta property="og:title" content="<?= esc($seo['title']) ?>">
    <meta property="og:description" content="<?= esc($seo['description']) ?>">
    <?php if (! empty($seo['image'])): ?>
        <meta property="og:image" content="<?= esc($seo['image'], 'attr') ?>">
    <?php endif; ?>

    <?php /* A favicon set from the admin panel, falling back to the shipped one. */ ?>
    <?php $rsIcon = $brand->identity['favicon'] ?? ''; ?>
    <?php if ($rsIcon !== ''): ?>
        <link rel="icon" href="<?= rs_url($rsIcon) ?>">
        <link rel="apple-touch-icon" href="<?= rs_url($rsIcon) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <?php endif; ?>
    <?php $rsOg = $brand->identity['og_image'] ?? ''; ?>
    <?php if ($rsOg !== ''): ?>
        <meta property="og:image" content="<?= rs_url($rsOg) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Karla:ital,wght@0,400;0,500;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= rs_asset('assets/css/app.css') ?>">

    <?php /* SweetAlert, for flash messages. On the storefront as well as the
             admin, so a confirmation looks the same in both. */ ?>
    <link rel="stylesheet" href="<?= rs_asset('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    <?php
    /*
     * Appearance settings as CSS custom properties.
     *
     * Inline rather than in the stylesheet because these are runtime values —
     * Tailwind compiles at build time and cannot know them. Every component is
     * written against these variables, so changing the page width or the card
     * minimum in the admin panel re-flows the site with no rebuild.
     *
     * DesignService clamps and pattern-checks every value before it gets here,
     * because this lands inside a style block.
     */
    ?>
    <style><?= service('design')->cssVariables() ?></style>
</head>
<body class="bg-shell text-ink">

<a class="rs-skip" href="#main">Skip to content</a>

<?php // include() shares this layout's data with the partial.
     // Partials needing their OWN data must use view($name, $data) instead —
     // include()'s second argument is renderer options, not view data. ?>
<?= $this->include('partials/header') ?>

<main id="main">
    <?= $this->include('partials/flash') ?>
    <?= $this->renderSection('content') ?>
</main>

<?= $this->include('partials/footer') ?>

<?php /* The cart drawer. Empty until something is added — its contents are
         fetched, so the page does not carry a basket it may never show. */ ?>
<div class="rs-cartdrawer" data-cart-drawer hidden role="dialog" aria-modal="true" aria-labelledby="rs-cart-title">
    <div class="rs-cartdrawer__scrim" data-drawer-close></div>

    <aside class="rs-cartdrawer__panel">
        <header class="flex items-center justify-between border-b border-shell-line px-6 py-5">
            <h2 class="font-display text-2xl" id="rs-cart-title">Your Cart</h2>
            <button type="button" class="rs-iconbtn" data-drawer-close aria-label="Close">
                <?= rs_icon('close', 'h-5 w-5') ?>
            </button>
        </header>

        <div class="flex flex-1 flex-col overflow-hidden" data-drawer-body>
            <p class="rs-help p-6">Loading&hellip;</p>
        </div>
    </aside>
</div>

<?= $this->include('partials/bulk_enquiry') ?>

<?= $this->include('partials/auth_modal') ?>

<?php /* Before app.js, and BOTH deferred — deferred scripts run in document
         order, so Swal is defined by the time the flash block looks for it. */ ?>
<script src="<?= rs_asset('assets/vendor/sweetalert2/sweetalert2.min.js') ?>" defer></script>
<script src="<?= rs_asset('assets/js/app.js') ?>" defer></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
