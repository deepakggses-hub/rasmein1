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

/*
 * Footer columns from settings: "Column | Label | /path", one per line.
 * Grouped in the order written, so reordering a column means moving a line.
 */
$columns = [];

foreach (preg_split('/\R/u', (string) service('settings')->get('footer_columns', '')) ?: [] as $line) {
    $parts = array_map('trim', explode('|', $line));

    if (count($parts) < 3 || $parts[0] === '' || $parts[1] === '') {
        continue;
    }

    // Absolute URLs are refused: a footer link is to this shop, and letting a
    // setting emit one makes the footer an open redirect surface.
    $href = str_starts_with($parts[2], '/') ? site_url(ltrim($parts[2], '/')) : null;

    if ($href === null) {
        continue;
    }

    $columns[$parts[0]][] = ['label' => $parts[1], 'url' => $href];
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


            <?php
            /*
             * Columns from settings, replacing three hand-written blocks.
             *
             * Falls back to the pages marked "show in footer" when nothing is
             * configured — a fresh install must not render an empty footer just
             * because nobody has opened the settings screen yet.
             */
            ?>
            <?php if ($columns !== []): ?>
                <?php foreach ($columns as $heading => $links): ?>
                    <nav aria-labelledby="foot-<?= esc(url_title($heading, '-', true), 'attr') ?>">
                        <p class="rs-foot__head" id="foot-<?= esc(url_title($heading, '-', true), 'attr') ?>">
                            <?= esc($heading) ?>
                        </p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <?php foreach ($links as $link): ?>
                                <li><a href="<?= esc($link['url'], 'attr') ?>"><?= esc($link['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endforeach; ?>
            <?php else: ?>
                <nav aria-labelledby="foot-company">
                    <p class="rs-foot__head" id="foot-company">Company</p>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <?php foreach (array_slice($pageLinks, 0, 6) as $page): ?>
                            <li><a href="<?= site_url('page/' . $page['slug']) ?>"><?= esc($page['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

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
