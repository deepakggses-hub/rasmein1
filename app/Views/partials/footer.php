<?php
/**
 * Storefront footer.
 *
 * Brand block plus four link columns, on the deep band. The columns come from
 * the CMS pages marked "show in footer" where they exist, so a shop can add a
 * policy page and have it appear without a developer; the shop column is built
 * from the live top-level categories for the same reason.
 */
$design = service('design');
$rsLogo = ($brand->identity['logo_light'] ?? '') ?: ($brand->identity['logo'] ?? '');

try {
    $shopLinks = model(\App\Models\CategoryModel::class)->childrenOf(null)          ;
    $shopLinks = array_slice($shopLinks, 0, 6);
} catch (Throwable) {
    $shopLinks = [];
}

try {
    $pageLinks = model(\App\Models\PageModel::class)->footerLinks();
} catch (Throwable) {
    $pageLinks = [];
}

$socialIcons = [
    'instagram' => 'instagram', 'facebook' => 'facebook', 'pinterest' => 'pinterest',
    'linkedin'  => 'linkedin',  'whatsapp' => 'whatsapp', 'youtube'  => 'arrow-right',
];
?>
<footer class="rs-foot">
    <div class="rs-shell">
        <div class="rs-foot__grid">

            <!-- Brand -->
            <div>
                <a href="<?= site_url('/') ?>" class="inline-block">
                    <?php if ($rsLogo !== ''): ?>
                        <img src="<?= rs_url($rsLogo) ?>" alt="<?= esc($brand->brandName, 'attr') ?>"
                             class="rs-logo rs-logo--footer" width="180" height="44" loading="lazy">
                    <?php else: ?>
                        <span class="block font-display text-2xl font-semibold text-shell">
                            Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                        </span>
                    <?php endif; ?>
                </a>

                <p class="mt-5 max-w-xs text-sm leading-relaxed">
                    <?= esc($brand->brandTagline) ?> Luxury wedding gifts, ritual collections and
                    premium hampers inspired by Indian heritage.
                </p>

                <?php if ($brand->social !== []): ?>
                    <ul class="mt-6 flex flex-wrap gap-2.5">
                        <?php foreach ($brand->social as $network => $url): ?>
                            <li>
                                <a href="<?= esc($url, 'attr') ?>" class="rs-foot__social"
                                   target="_blank" rel="noopener noreferrer"
                                   aria-label="<?= esc(ucfirst($network), 'attr') ?>">
                                    <?= rs_icon($socialIcons[$network] ?? 'arrow-right', 'h-4 w-4') ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Shop -->
            <nav aria-labelledby="foot-shop">
                <p class="rs-foot__head" id="foot-shop">Shop</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <?php foreach ($shopLinks as $category): ?>
                        <li>
                            <a href="<?= rs_url((string) ($category->path ?? $category->slug)) ?>">
                                <?= esc($category->name) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="<?= site_url('build') ?>">Build a gift box</a></li>
                    <li><a href="<?= site_url('shop') ?>">Everything</a></li>
                </ul>
            </nav>

            <!-- Company -->
            <nav aria-labelledby="foot-company">
                <p class="rs-foot__head" id="foot-company">Company</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <?php foreach (array_slice($pageLinks, 0, 5) as $page): ?>
                        <li><a href="<?= site_url('page/' . $page['slug']) ?>"><?= esc($page['title']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?= site_url('collections') ?>">Collections</a></li>
                </ul>
            </nav>

            <!-- Care -->
            <nav aria-labelledby="foot-care">
                <p class="rs-foot__head" id="foot-care">Care</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="<?= site_url('account/orders') ?>">Track an order</a></li>
                    <li><a href="<?= site_url('account') ?>">Your account</a></li>
                    <li><a href="<?= site_url('enquiry') ?>">Bulk enquiries</a></li>
                    <?php foreach (array_slice($pageLinks, 5, 3) as $page): ?>
                        <li><a href="<?= site_url('page/' . $page['slug']) ?>"><?= esc($page['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- Reach us -->
            <div>
                <p class="rs-foot__head">Reach us</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li>
                        <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>"><?= esc($brand->supportEmail) ?></a>
                    </li>
                    <li>
                        <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $brand->supportPhone), 'attr') ?>" class="num">
                            <?= esc($brand->supportPhone) ?>
                        </a>
                    </li>
                    <?php if (! empty($brand->identity['address'])): ?>
                        <li class="pt-2 leading-relaxed"><?= nl2br(esc($brand->identity['address'])) ?></li>
                    <?php endif; ?>
                    <?php if (! empty($brand->identity['gstin'])): ?>
                        <li class="num pt-2 font-mono text-xs">GSTIN <?= esc($brand->identity['gstin']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 py-6 text-xs">
            <p>
                &copy; <?= date('Y') ?> <?= esc($brand->identity['legal_name'] ?: $brand->brandName) ?>.
                Traditions crafted beautifully. Made with care in India.
            </p>
            <p class="font-mono tracking-[0.2em] text-white/40 uppercase">Est. Twenty Twenty-Four</p>
        </div>
    </div>
</footer>
