<?php
/**
 * Storefront header.
 *
 * Dark band, wordmark or uploaded logo, centred navigation, search, and the
 * three account icons. Below 1100px the navigation collapses into a drawer —
 * that breakpoint is where six uppercase items plus a search field stop fitting
 * honestly rather than where a device category begins.
 *
 * The menu comes from the `design_nav` setting so it can be edited without a
 * developer.
 */
$design = service('design');

$navItems = [];

foreach (preg_split('/\R/', $design->get('design_nav', '')) ?: [] as $line) {
    $line = trim((string) $line);

    if ($line === '' || ! str_contains($line, '|')) {
        continue;
    }

    [$label, $path] = array_map('trim', explode('|', $line, 2));

    if ($label === '' || $path === '') {
        continue;
    }

    // Only same-site paths: an editable menu that accepts absolute URLs is a
    // way to point a shop's own navigation at somewhere else.
    if (preg_match('#^[a-z]+://#i', $path) === 1) {
        continue;
    }

    $navItems[] = ['label' => $label, 'path' => '/' . ltrim($path, '/')];
}

if ($navItems === []) {
    $navItems = [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Shop', 'path' => '/shop'],
        ['label' => 'Collections', 'path' => '/collections'],
    ];
}

/*
 * The phrases the search box types through. Editable copy, one per line, so a
 * shop can advertise what it actually sells rather than a generic prompt.
 */
$searchPhrases = array_values(array_filter(array_map(
    'trim',
    preg_split('/\r\n|\r|\n/', (string) service('settings')->get('search_placeholders', '')) ?: []
)));

if ($searchPhrases === []) {
    $searchPhrases = ['What are you looking for?'];
}

$cartCount = 0;

/*
 * The saved count, for the header badge. Read here rather than passed in,
 * because every storefront page includes this partial and threading one number
 * through every controller would be worse.
 */
$wishCount = 0;

try {
    $wishCount = model(\App\Models\WishlistModel::class)->forViewer(
        session('customer_id') !== null ? (int) session('customer_id') : null,
        service('visitor')->peek()
    )->countAllResults();
} catch (\Throwable $e) {
    // A header must never be the thing that takes a page down.
    log_message('error', 'Wishlist count failed: {m}', ['m' => $e->getMessage()]);
}

try {
    $cartCount = service('cart')->itemCount();
} catch (Throwable) {
    // A header must never be the thing that takes a page down.
}

$rsLogo    = $brand->identity['logo'] ?? '';
$signedIn  = session('customer_id') !== null;
$current   = '/' . trim((string) uri_string(), '/');
?>
<header class="rs-head <?= $design->flag('design_sticky_header') ? 'rs-head--sticky' : '' ?>">
    <div class="rs-shell">
        <div class="rs-head__bar">

            <!-- Menu trigger, below the navigation breakpoint only. -->
            <button type="button" class="rs-iconbtn shrink-0 min-[1100px]:hidden" data-drawer-open
                    aria-label="Open the menu" aria-controls="rs-drawer" aria-expanded="false">
                <?= rs_icon('menu', 'h-5 w-5') ?>
            </button>

            <a href="<?= site_url('/') ?>" class="shrink-0" aria-label="<?= esc($brand->brandName, 'attr') ?> — home">
                <?php if ($rsLogo !== ''): ?>
                    <img src="<?= rs_url($rsLogo) ?>" alt="<?= esc($brand->brandName, 'attr') ?>"
                         class="rs-logo rs-logo--header" width="180" height="44">
                <?php else: ?>
                    <span class="block font-display text-2xl leading-none font-semibold text-shell sm:text-[1.75rem]">
                        Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                    </span>
                    <span class="mt-0.5 block font-mono text-[0.5rem] tracking-[0.3em] text-brass uppercase">
                        <?= esc(rs_excerpt($brand->brandTagline, 26)) ?>
                    </span>
                <?php endif; ?>
            </a>

            <nav class="rs-head__nav" aria-label="Primary">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $isActive = $item['path'] === '/'
                        ? $current === '/'
                        : str_starts_with($current, rtrim($item['path'], '/'));
                    ?>
                    <a href="<?= site_url(ltrim($item['path'], '/')) ?>"
                       class="rs-head__link <?= $isActive ? 'is-active' : '' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= esc($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="<?= site_url('search') ?>" method="get" class="rs-head__search" role="search">
                <label for="rs-search" class="sr-only">Search the shop</label>
                <input id="rs-search" type="search" name="q" class="rs-head__input"
                       placeholder="<?= esc($searchPhrases[0] ?? 'What are you looking for?', 'attr') ?>"
                       autocomplete="off" data-typer
                       data-phrases="<?= esc(json_encode($searchPhrases), 'attr') ?>">
                <button type="submit" class="rs-head__submit" aria-label="Search">
                    <?= rs_icon('search', 'h-4 w-4') ?>
                </button>
            </form>

            <div class="rs-head__icons">
                <!-- Search, for the widths where the field is hidden. -->
                <a href="<?= site_url('search') ?>" class="rs-iconbtn min-[900px]:hidden" aria-label="Search">
                    <?= rs_icon('search', 'h-5 w-5') ?>
                </a>

                <?php
                /*
                 * The corporate switch.
                 *
                 * A POST, because it changes how the whole site behaves and a
                 * GET that does that can be fired by any image tag on any page.
                 * It carries the current path so switching mid-browse does not
                 * throw the visitor back to the homepage.
                 */
                $isEnquire = rs_is_enquire_mode();
                ?>
                <form method="post" action="<?= site_url('mode') ?>" class="rs-modeswitch">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc(uri_string(), 'attr') ?>">
                    <input type="hidden" name="mode"
                           value="<?= $isEnquire ? \Config\Rasmein::MODE_BUY : \Config\Rasmein::MODE_ENQUIRE ?>">

                    <button type="submit" class="rs-modeswitch__btn" aria-pressed="<?= $isEnquire ? 'true' : 'false' ?>"
                            title="<?= $isEnquire ? 'Back to ordinary shopping' : 'Switch to corporate gifting' ?>">
                        <span class="rs-modeswitch__track" aria-hidden="true">
                            <span class="rs-modeswitch__knob"></span>
                        </span>
                        <span class="rs-modeswitch__label">
                            <?= esc(service('settings')->get($isEnquire ? 'corporate_label_on' : 'corporate_label_off', '')
                                ?: ($isEnquire ? 'Corporate' : 'Corporate')) ?>
                        </span>
                    </button>
                </form>

                <a href="<?= site_url('wishlist') ?>" class="rs-iconbtn relative"
                   aria-label="Wishlist<?= ($wishCount ?? 0) > 0 ? ', ' . $wishCount . ' saved' : '' ?>">
                    <?= rs_icon('heart', 'h-5 w-5') ?>
                    <?php /* Updated in place by the script when a heart is
                             tapped, so the header agrees with the page without
                             a reload. */ ?>
                    <span class="rs-count num" data-wish-count
                          <?= ($wishCount ?? 0) > 0 ? '' : 'hidden' ?>><?= (int) ($wishCount ?? 0) ?></span>
                </a>

                <a href="<?= site_url('cart') ?>" class="rs-iconbtn" aria-label="Basket<?= $cartCount > 0 ? ', ' . $cartCount . ' items' : '' ?>">
                    <?= rs_icon('bag', 'h-5 w-5') ?>
                    <?php /* Always rendered, hidden when empty. The script
                             updates it in place, and a node that does not exist
                             cannot be updated — the badge stayed missing after
                             the first add. */ ?>
                    <span class="rs-iconbtn__count" data-cart-count
                          <?= $cartCount > 0 ? '' : 'hidden' ?>><?= $cartCount > 99 ? '99+' : $cartCount ?></span>
                </a>

                <a href="<?= site_url($signedIn ? 'account' : 'account/login') ?>" class="rs-iconbtn"
                   aria-label="<?= $signedIn ? 'Your account' : 'Sign in' ?>">
                    <?= rs_icon('user', 'h-5 w-5') ?>
                </a>
            </div>
        </div>
    </div>
</header>

<?php if (rs_is_enquire_mode()): ?>
    <?php /* Never leave someone in a changed mode without saying so — a shop
             that suddenly has no Add to Cart looks broken otherwise. */ ?>
    <div class="rs-modebar">
        <?= esc(service('settings')->get('corporate_banner', '')
            ?: 'Corporate gifting — add pieces to an enquiry and we will quote.') ?>
        <a href="<?= site_url('cart') ?>" class="ml-2"><?= esc(rs_cta_label(null, 'cart')) ?></a>
    </div>
<?php endif; ?>

<!-- Drawer, rendered once and moved by CSS rather than injected by script. -->
<div id="rs-drawer" class="rs-drawer" data-drawer hidden>
    <div class="flex items-center justify-between pb-3">
        <span class="rs-kicker rs-kicker--bare text-brass">Menu</span>
        <button type="button" class="rs-iconbtn" data-drawer-close aria-label="Close the menu">
            <?= rs_icon('close', 'h-5 w-5') ?>
        </button>
    </div>

    <form action="<?= site_url('search') ?>" method="get" class="relative mb-4" role="search">
        <label for="rs-search-drawer" class="sr-only">Search the shop</label>
        <input id="rs-search-drawer" type="search" name="q" class="rs-head__input"
               placeholder="What are you looking for?">
        <button type="submit" class="rs-head__submit" aria-label="Search">
            <?= rs_icon('search', 'h-4 w-4') ?>
        </button>
    </form>

    <nav aria-label="Primary, mobile">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= site_url(ltrim($item['path'], '/')) ?>" class="rs-drawer__link"><?= esc($item['label']) ?></a>
        <?php endforeach; ?>
        <a href="<?= site_url('build') ?>" class="rs-drawer__link">Build a gift box</a>

                <?php /* The drawer's copy: the header switch is hidden below
                         900px, where the icon row has no room for it. */ ?>
                <form method="post" action="<?= site_url('mode') ?>"
                      class="mt-5 border-t border-shell-line/20 pt-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc(uri_string(), 'attr') ?>">
                    <input type="hidden" name="mode"
                           value="<?= rs_is_enquire_mode() ? \Config\Rasmein::MODE_BUY : \Config\Rasmein::MODE_ENQUIRE ?>">
                    <button type="submit" class="rs-modeswitch__btn !flex"
                            aria-pressed="<?= rs_is_enquire_mode() ? 'true' : 'false' ?>">
                        <span class="rs-modeswitch__track" aria-hidden="true">
                            <span class="rs-modeswitch__knob"></span>
                        </span>
                        <span class="rs-modeswitch__label">Corporate gifting</span>
                    </button>
                </form>
        <a href="<?= site_url($signedIn ? 'account' : 'account/login') ?>" class="rs-drawer__link">
            <?= $signedIn ? 'Your account' : 'Sign in' ?>
        </a>
    </nav>
</div>
<div class="rs-scrim" data-drawer-scrim hidden></div>
