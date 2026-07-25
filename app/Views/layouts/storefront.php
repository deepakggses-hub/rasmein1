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

    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Eczar:wght@400;500;600;700&family=Karla:ital,wght@0,400;0,500;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= rs_asset('assets/css/app.css') ?>">
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

<script src="<?= rs_asset('assets/js/app.js') ?>" defer></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
