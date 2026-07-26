<?php
/**
 * Admin shell.
 *
 * LAYOUT
 *
 * A fixed top bar over an icon sidebar. The bar carries the things wanted from
 * every screen — search, notifications, the account menu — so they stop being
 * buried at the bottom of a scrolling sidebar where the previous version put
 * them. The sidebar collapses to icons on demand, which is the difference
 * between a comfortable table and a cramped one on a 13" laptop.
 *
 * Denser and plainer than the storefront on purpose: this is a tool used all
 * day, not a place to be charmed.
 *
 * The collapse preference is remembered in localStorage, so it survives a page
 * load. That is a display preference, not data, which is why it does not
 * warrant a round trip to the server.
 *
 * @var array<string, mixed>|null $admin
 * @var array<int, array<string, mixed>> $nav
 */
$pageTitle   = $pageTitle   ?? 'Admin';
$admin       = $admin       ?? null;
$nav         = $nav         ?? [];
$journeyMode = $journeyMode ?? \Config\Rasmein::MODE_BUY;
$isEnquire   = $journeyMode === \Config\Rasmein::MODE_ENQUIRE;

$unread = 0;
foreach ($nav as $group) {
    foreach ($group['items'] as $item) {
        if (($item['icon'] ?? '') === 'bell') { $unread = (int) ($item['badge'] ?? 0); }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title><?= esc($pageTitle) ?> · Rasmein admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Eczar:wght@500;600&family=Karla:wght@400;500;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= rs_asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= rs_asset('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    <?php if (! empty($needsEditor)): ?>
        <link rel="stylesheet" href="<?= rs_asset('assets/vendor/quill/quill.snow.css') ?>">
    <?php endif; ?>
</head>
<body class="h-full bg-shell-deep text-ink">

<a class="rs-skip" href="#admin-main">Skip to content</a>

<div class="rs-admin" data-admin-shell>

    <!-- ============================== top bar ============================= -->
    <header class="rs-topbar">
        <div class="flex items-center gap-3">
            <button type="button" class="rs-icon-btn lg:hidden" data-nav-open
                    aria-label="Open menu" aria-controls="admin-nav" aria-expanded="false">
                <?= rs_icon('menu', 'h-5 w-5') ?>
            </button>

            <button type="button" class="rs-icon-btn hidden lg:inline-flex" data-nav-collapse
                    aria-label="Collapse the menu">
                <?= rs_icon('menu', 'h-5 w-5') ?>
            </button>

            <a href="<?= site_url('admin') ?>" class="flex items-baseline gap-2">
                <span class="font-display text-xl leading-none font-semibold text-shell">
                    Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                </span>
                <span class="hidden font-mono text-[0.5625rem] tracking-[0.26em] text-brass uppercase sm:inline">Admin</span>
            </a>
        </div>

        <!-- Search jumps straight to the two lists people actually hunt in. -->
        <form action="<?= site_url('admin/orders') ?>" method="get" class="rs-topbar__search" role="search">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-shell/40">
                <?= rs_icon('search', 'h-4 w-4') ?>
            </span>
            <input type="search" name="q" placeholder="Search orders by reference, name or phone"
                   aria-label="Search orders" class="rs-topbar__input">
        </form>

        <div class="flex items-center gap-1.5">
            <span class="hidden xl:inline">
                <?php if ($isEnquire): ?>
                    <span class="rs-badge rs-badge--enquire">Enquire mode</span>
                <?php else: ?>
                    <span class="rs-badge rs-badge--brass">Buy mode</span>
                <?php endif; ?>
            </span>

            <a href="<?= site_url('/') ?>" class="rs-icon-btn" title="View the storefront" target="_blank" rel="noopener">
                <?= rs_icon('store', 'h-5 w-5') ?>
                <span class="sr-only">View the storefront</span>
            </a>

            <a href="<?= site_url('admin/notifications') ?>" class="rs-icon-btn relative" title="Notifications">
                <?= rs_icon('bell', 'h-5 w-5') ?>
                <?php if ($unread > 0): ?>
                    <span class="rs-dot num"><?= $unread > 99 ? '99+' : $unread ?></span>
                <?php endif; ?>
                <span class="sr-only"><?= $unread ?> unread notifications</span>
            </a>

            <div class="rs-menu" data-menu>
                <button type="button" class="rs-menu__trigger" data-menu-trigger
                        aria-haspopup="true" aria-expanded="false">
                    <span class="rs-avatar"><?= esc(mb_strtoupper(mb_substr((string) ($admin['name'] ?? '?'), 0, 1))) ?></span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-xs leading-tight font-medium text-shell"><?= esc($admin['name'] ?? '') ?></span>
                        <span class="block font-mono text-[0.5625rem] tracking-[0.12em] text-shell/50 uppercase"><?= esc($admin['role_name'] ?? '') ?></span>
                    </span>
                </button>

                <div class="rs-menu__panel" data-menu-panel hidden>
                    <a href="<?= site_url('admin/password') ?>" class="rs-menu__item">Change password</a>
                    <a href="<?= site_url('admin/notifications') ?>" class="rs-menu__item">Notifications<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
                    <a href="<?= site_url('/') ?>" class="rs-menu__item" target="_blank" rel="noopener">View the storefront</a>
                    <form method="post" action="<?= site_url('admin/logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="rs-menu__item rs-menu__item--danger w-full text-left">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- ============================== sidebar ============================= -->
    <nav id="admin-nav" class="rs-sidebar" data-nav aria-label="Admin">
        <div class="flex items-center justify-between px-4 py-3 lg:hidden">
            <span class="rs-eyebrow rs-eyebrow--plain text-shell/60">Menu</span>
            <button type="button" class="rs-icon-btn" data-nav-close aria-label="Close the menu">
                <?= rs_icon('close', 'h-5 w-5') ?>
            </button>
        </div>

        <?php foreach ($nav as $group): ?>
            <p class="rs-sidebar__group"><?= esc($group['group']) ?></p>
            <ul>
                <?php foreach ($group['items'] as $item): ?>
                    <?php $active = rs_active($item['match'], 'is-active'); ?>
                    <li>
                        <a href="<?= site_url($item['url']) ?>"
                           class="rs-sidebar__link <?= $active ?>"
                           title="<?= esc($item['label'], 'attr') ?>">
                            <?= rs_icon($item['icon'] ?? 'dashboard') ?>
                            <span class="rs-sidebar__text"><?= esc($item['label']) ?></span>
                            <?php if (! empty($item['badge'])): ?>
                                <span class="rs-sidebar__badge num"><?= (int) $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <div class="rs-sidebar__foot">
            <p class="rs-eyebrow rs-eyebrow--plain text-shell/40">Store journey</p>
            <p class="mt-1.5">
                <?php if ($isEnquire): ?>
                    <span class="rs-badge rs-badge--enquire">Enquire now</span>
                <?php else: ?>
                    <span class="rs-badge rs-badge--brass">Buy now</span>
                <?php endif; ?>
            </p>
        </div>
    </nav>

    <div class="rs-scrim" data-nav-scrim hidden></div>

    <!-- =============================== main ============================== -->
    <main id="admin-main" class="rs-main">
        <?= view('admin/partials/flash') ?>
        <?= $this->renderSection('content') ?>
    </main>
</div>

<script src="<?= rs_asset('assets/vendor/sweetalert2/sweetalert2.min.js') ?>" defer></script>
<script src="<?= rs_asset('assets/js/app.js') ?>" defer></script>
<script src="<?= rs_asset('assets/js/admin.js') ?>" defer></script>
<?php if (! empty($needsEditor)): ?>
    <script src="<?= rs_asset('assets/vendor/quill/quill.js') ?>" defer></script>
    <script src="<?= rs_asset('assets/js/editor.js') ?>" defer></script>
<?php endif; ?>
<?php if (! empty($needsCharts)): ?>
    <script src="<?= rs_asset('assets/vendor/chartjs/chart.umd.js') ?>" defer></script>
    <script src="<?= rs_asset('assets/js/charts.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
