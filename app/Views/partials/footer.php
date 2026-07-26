<?php
/**
 * @var array<int, array<string, mixed>> $footerPages
 * @var object $brand
 */
?>
<footer class="mt-24 bg-ink text-shell/75">
    <div class="rs-shell py-14">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">

            <div class="lg:col-span-2">
                <p class="font-display text-2xl font-semibold text-shell">
                    <?php
                        /*
                         * An uploaded logo replaces the wordmark. The wordmark
                         * is not deleted — a shop without a logo file should
                         * still look deliberate rather than empty, and this is
                         * what it looked like before anyone uploaded anything.
                         */
                        $rsLogo = $brand->identity['logo_light'] ?? '';
                        $rsLogo = $rsLogo !== '' ? $rsLogo : ($brand->identity['logo'] ?? '');
                    ?>
                    <?php if ($rsLogo !== ''): ?>
                        <img src="<?= rs_url($rsLogo) ?>"
                             alt="<?= esc($brand->brandName, 'attr') ?>"
                             class="h-8 w-auto object-contain sm:h-9">
                    <?php else: ?>
                        Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                    <?php endif; ?>
                </p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed">
                    A gifting studio built on one idea: the box should feel as considered
                    as what goes inside it. Curated in India, packed by hand.
                </p>
                <hr class="rs-rule my-6 max-w-xs">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex gap-2">
                        <dt class="sr-only">Email</dt>
                        <dd><a class="rs-link" href="mailto:<?= esc($brand->supportEmail, 'attr') ?>"><?= esc($brand->supportEmail) ?></a></dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="sr-only">Phone</dt>
                        <dd class="font-mono"><?= esc($brand->supportPhone) ?></dd>
                    </div>
                </dl>
            </div>

            <div>
                <p class="rs-eyebrow rs-eyebrow--plain rs-eyebrow--on-dark">Shop</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a class="rs-link" href="<?= site_url('build') ?>">Build your own box</a></li>
                    <li><a class="rs-link" href="<?= site_url('gift-boxes') ?>">Ready hampers</a></li>
                    <li><a class="rs-link" href="<?= site_url('shop') ?>">All products</a></li>
                    <li><a class="rs-link" href="<?= site_url('collections') ?>">Collections</a></li>
                    <li><a class="rs-link" href="<?= site_url('page/corporate-gifting') ?>">Corporate gifting</a></li>
                </ul>
            </div>

            <div>
                <p class="rs-eyebrow rs-eyebrow--plain rs-eyebrow--on-dark">Help</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <?php foreach ($footerPages as $page): ?>
                        <li>
                            <a class="rs-link" href="<?= site_url('page/' . $page['slug']) ?>">
                                <?= esc($page['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a class="rs-link" href="<?= site_url('account') ?>">Your account</a></li>
                </ul>
            </div>
        </div>

        <hr class="rs-rule mt-12">

        <div class="mt-6 flex flex-col gap-3 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= date('Y') ?> <?= esc($brand->brandName) ?>. All rights reserved.</p>
            <p class="font-mono tracking-wider text-shell/50">
                Made in India · Prices in <?= esc($brand->currency) ?>
            </p>
        </div>
    </div>
</footer>
