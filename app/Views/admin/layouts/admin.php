<?php
/**
 * Admin shell.
 *
 * Denser and plainer than the storefront on purpose — this is a tool used all
 * day, not a place to be charmed. Same palette so it still reads as Rasmein,
 * but tighter spacing, mono for every figure, and no decoration.
 *
 * @var array<string, mixed>|null $admin
 * @var array<int, array<string, mixed>> $nav
 */

/*
 * Every admin screen should reach this layout through
 * AdminController::adminPage(), which supplies the four variables below. These
 * fallbacks exist so that forgetting to do so renders a usable page with an
 * empty nav instead of a white screen — which is exactly what happened on the
 * forced password-change screen, reported from the field.
 */
$pageTitle   = $pageTitle   ?? 'Admin';
$admin       = $admin       ?? null;
$nav         = $nav         ?? [];
$journeyMode = $journeyMode ?? \Config\Rasmein::MODE_BUY;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle) ?> · Rasmein admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Eczar:wght@500;600&family=Karla:wght@400;500;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= rs_asset('assets/css/app.css') ?>">
</head>
<body class="bg-shell-deep text-ink">

<a class="rs-skip" href="#admin-main">Skip to content</a>

<div class="lg:grid lg:min-h-screen lg:grid-cols-[15rem_1fr]">

    <!-- ------------------------------------------------------- sidebar -->
    <div class="bg-ink text-shell/75 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center justify-between px-5 py-4 lg:block">
            <a href="<?= site_url('admin') ?>" class="block">
                <span class="font-display text-xl leading-none font-semibold text-shell">
                    Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                </span>
                <span class="mt-0.5 block font-mono text-[0.5625rem] tracking-[0.26em] text-brass uppercase">Admin</span>
            </a>
            <button type="button" class="p-2 text-shell/70 lg:hidden" data-menu-trigger
                    aria-expanded="false" aria-controls="admin-nav" aria-label="Open menu">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- The journey switch state, permanently visible. It changes how the
             whole store sells, so it should never be a surprise. -->
        <div class="mx-5 mb-4 border border-brass/30 px-3 py-2.5">
            <p class="font-mono text-[0.5625rem] tracking-[0.16em] text-brass uppercase">Store journey</p>
            <p class="mt-1.5">
                <?php if ($journeyMode === \Config\Rasmein::MODE_ENQUIRE): ?>
                    <span class="rs-badge rs-badge--enquire">Enquire now</span>
                <?php else: ?>
                    <span class="rs-badge rs-badge--brass">Buy now</span>
                <?php endif; ?>
            </p>
        </div>

        <nav id="admin-nav" class="hidden px-3 pb-6 lg:block" data-menu-panel aria-label="Admin">
            <?php foreach ($nav as $group): ?>
                <p class="px-2 pt-4 pb-1.5 font-mono text-[0.5625rem] tracking-[0.16em] text-shell/40 uppercase">
                    <?= esc($group['group']) ?>
                </p>
                <ul>
                    <?php foreach ($group['items'] as $item): ?>
                        <li>
                            <a href="<?= site_url($item['url']) ?>"
                               class="flex items-center justify-between gap-2 rounded-sm px-2 py-2 text-sm <?= rs_active($item['match'], 'bg-mulberry text-shell') ?: 'hover:bg-ink-soft hover:text-shell' ?>">
                                <span><?= esc($item['label']) ?></span>
                                <?php if (! empty($item['badge'])): ?>
                                    <span class="num rs-badge rs-badge--brass"><?= (int) $item['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>

            <hr class="rs-rule my-5">

            <div class="px-2">
                <p class="text-sm font-medium text-shell"><?= esc($admin['name'] ?? '') ?></p>
                <p class="font-mono text-[0.5625rem] tracking-[0.14em] text-shell/50 uppercase">
                    <?= esc($admin['role_name'] ?? '') ?>
                </p>
                <div class="mt-3 flex flex-col gap-1.5 text-sm">
                    <a href="<?= site_url('admin/password') ?>" class="rs-link text-shell/70">Change password</a>
                    <a href="<?= site_url('/') ?>" class="rs-link text-shell/70">View storefront</a>
                    <form method="post" action="<?= site_url('admin/logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="rs-link text-left text-shell/70 hover:text-rose">Sign out</button>
                    </form>
                </div>
            </div>
        </nav>
    </div>

    <!-- ---------------------------------------------------------- main -->
    <main id="admin-main" class="min-w-0">
        <?= view('admin/partials/flash') ?>
        <?= $this->renderSection('content') ?>
    </main>
</div>

<script src="<?= rs_asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
