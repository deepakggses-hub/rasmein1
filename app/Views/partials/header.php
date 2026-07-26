<?php
/**
 * @var array<int, \App\Entities\Category> $navCategories
 * @var bool   $isEnquire
 * @var object $brand
 */
$cartLabel = rs_cta_label(null, 'cart');
?>
<header class="sticky top-0 z-40">

    <!-- Announcement strip. In Enquire mode it states plainly what will
         happen when you add something, so the change of journey is never
         a surprise at checkout. -->
    <div class="bg-ink text-shell/85">
        <div class="rs-shell flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-2 text-xs">
            <p class="flex items-center gap-2">
                <?php if ($isEnquire): ?>
                    <span class="rs-badge rs-badge--enquire">Enquiry mode</span>
                    <span>Add what you like and we'll send you a quote — nothing is charged online.</span>
                <?php else: ?>
                    <span class="rs-badge rs-badge--brass">Free delivery</span>
                    <span>On orders above <?= rs_money(1500) ?>. Packed and dispatched within 48 hours.</span>
                <?php endif; ?>
            </p>
            <p class="hidden font-mono tracking-wider sm:block">
                <a class="rs-link" href="tel:<?= esc(preg_replace('/\s+/', '', $brand->supportPhone), 'attr') ?>">
                    <?= esc($brand->supportPhone) ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Main bar -->
    <div class="border-b border-shell-line bg-shell/95 backdrop-blur-sm">
        <div class="rs-shell flex items-center justify-between gap-4 py-4">

            <!-- Wordmark. Set in Eczar with a brass diacritic dot over the 'i'
                 stem — the one piece of lettering detail we allow ourselves. -->
            <a href="<?= site_url('/') ?>" class="group shrink-0" aria-label="<?= esc($brand->brandName) ?> home">
                <span class="block font-display text-2xl leading-none font-semibold tracking-tight text-mulberry md:text-[1.75rem]">
                    <?php
                        /*
                         * An uploaded logo replaces the wordmark. The wordmark
                         * is not deleted — a shop without a logo file should
                         * still look deliberate rather than empty, and this is
                         * what it looked like before anyone uploaded anything.
                         */
                        $rsLogo = $brand->identity['logo'] ?? '';
                        
                    ?>
                    <?php if ($rsLogo !== ''): ?>
                        <img src="<?= rs_url($rsLogo) ?>"
                             alt="<?= esc($brand->brandName, 'attr') ?>"
                             class="h-8 w-auto object-contain sm:h-9">
                    <?php else: ?>
                        Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                    <?php endif; ?>
                </span>
                <span class="mt-0.5 block font-mono text-[0.5625rem] tracking-[0.28em] text-ink-muted uppercase">
                    Gifting studio
                </span>
            </a>

            <!-- Desktop nav -->
            <nav class="hidden items-center gap-7 text-sm font-medium lg:flex" aria-label="Main">
                <a href="<?= site_url('build') ?>" class="rs-link <?= rs_active('build', 'text-mulberry') ?>">
                    Build a box
                </a>
                <a href="<?= site_url('gift-boxes') ?>" class="rs-link <?= rs_active('gift-boxes', 'text-mulberry') ?>">
                    Ready hampers
                </a>

                <?php if ($navCategories !== []): ?>
                    <div class="relative" data-dropdown>
                        <button type="button"
                                class="rs-link flex items-center gap-1.5 <?= rs_active('shop', 'text-mulberry') ?>"
                                aria-expanded="false"
                                aria-haspopup="true"
                                data-dropdown-trigger>
                            Shop
                            <svg class="h-3 w-3 transition-transform" viewBox="0 0 12 12" fill="none" aria-hidden="true" data-dropdown-chevron>
                                <path d="M2.5 4.5 6 8l3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <div class="absolute left-0 top-full hidden w-60 pt-3" data-dropdown-panel>
                            <ul class="border border-shell-line bg-white py-2 shadow-[var(--shadow-lift)]">
                                <?php foreach ($navCategories as $category): ?>
                                    <li>
                                        <a href="<?= $category->url() ?>"
                                           class="block px-4 py-2 text-sm hover:bg-shell-deep hover:text-mulberry">
                                            <?= esc($category->name) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li class="mt-1 border-t border-shell-line pt-1">
                                    <a href="<?= site_url('shop') ?>" class="block px-4 py-2 font-mono text-[0.6875rem] tracking-widest text-brass uppercase hover:bg-shell-deep">
                                        Everything
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="<?= site_url('collections') ?>" class="rs-link <?= rs_active('collections', 'text-mulberry') ?>">
                    Collections
                </a>
                <a href="<?= site_url('page/corporate-gifting') ?>" class="rs-link">Corporate</a>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-1">
                <a href="<?= site_url('search') ?>" class="p-2.5 text-ink-soft hover:text-mulberry" aria-label="Search">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M13.5 13.5 17 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </a>
                <a href="<?= site_url('wishlist') ?>" class="hidden p-2.5 text-ink-soft hover:text-mulberry sm:block" aria-label="Wishlist">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 16.5S3.5 12.7 3.5 8.4A3.4 3.4 0 0 1 10 6.6a3.4 3.4 0 0 1 6.5 1.8c0 4.3-6.5 8.1-6.5 8.1Z"
                              stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="<?= $isEnquire ? site_url('enquiry') : site_url('cart') ?>"
                   class="flex items-center gap-2 p-2.5 text-ink-soft hover:text-mulberry"
                   aria-label="<?= esc($cartLabel) ?>">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M3.5 6h13l-1.1 8.4a1.5 1.5 0 0 1-1.5 1.3H6.1a1.5 1.5 0 0 1-1.5-1.3L3.5 6Z"
                              stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M7.2 6V4.8a2.8 2.8 0 0 1 5.6 0V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="hidden font-mono text-[0.6875rem] tracking-widest uppercase xl:inline">
                        <?= esc($cartLabel) ?>
                    </span>
                </a>

                <button type="button"
                        class="ml-1 p-2.5 text-ink-soft lg:hidden"
                        aria-label="Open menu"
                        aria-expanded="false"
                        aria-controls="mobile-nav"
                        data-menu-trigger>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile nav -->
    <div id="mobile-nav" class="hidden border-b border-shell-line bg-white lg:hidden" data-menu-panel>
        <nav class="rs-shell flex flex-col py-2" aria-label="Mobile">
            <a href="<?= site_url('build') ?>" class="border-b border-shell-line py-3 font-medium">Build a box</a>
            <a href="<?= site_url('gift-boxes') ?>" class="border-b border-shell-line py-3 font-medium">Ready hampers</a>
            <?php foreach ($navCategories as $category): ?>
                <a href="<?= $category->url() ?>" class="border-b border-shell-line py-3 text-ink-soft">
                    <?= esc($category->name) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= site_url('collections') ?>" class="border-b border-shell-line py-3 font-medium">Collections</a>
            <a href="<?= site_url('page/corporate-gifting') ?>" class="py-3 font-medium">Corporate gifting</a>
        </nav>
    </div>
</header>
