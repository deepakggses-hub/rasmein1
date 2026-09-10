<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The corporate landing page.
 *
 * Every section hides itself when empty, so a shop can fill in as much as it
 * has and the page reads as sparse rather than broken.
 *
 * @var array $data
 * @var array $banners
 * @var array $occasions
 * @var array $rows       product rows, each already carrying its pieces
 */
$d = $data ?? [];
$g = static fn (string $s, string $f, string $fb = ''): string => trim((string) ($d[$s][$f] ?? '')) !== ''
    ? (string) $d[$s][$f]
    : $fb;

/* A path on this site, or nothing. Never an absolute URL from a setting. */
$safe = static fn (string $p): ?string => str_starts_with(trim($p), '/') ? site_url(ltrim(trim($p), '/')) : null;
?>

<!-- ================================================================== BANNER -->
<?php if (($banners ?? []) !== []): ?>
    <?php /* The homepage's own banner component, so both behave identically and
             a change to one is a change to both. */ ?>
    <?= view('partials/hero_banners', ['heroSlides' => $banners]) ?>
<?php endif; ?>

<!-- ================================================================= MARQUEE -->
<?php
$marquee = array_values(array_filter(array_map(
    'trim',
    preg_split('/[\r\n·|]+/u', $g('marquee', 'lines')) ?: []
)));
?>
<?php if ($marquee !== []): ?>
    <div class="rs-marquee rs-bleed">
        <div class="rs-marquee__track">
            <?php /* Twice through, which is what makes the loop seamless. */ ?>
            <?php for ($pass = 0; $pass < 2; $pass++): ?>
                <span class="rs-marquee__item" <?= $pass === 1 ? 'aria-hidden="true"' : '' ?>>
                    <?php foreach ($marquee as $i => $phrase): ?>
                        <?php if ($i > 0): ?>
                            <span class="text-brass" aria-hidden="true">&middot;</span>
                        <?php endif; ?>
                        <span><?= esc($phrase) ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<!-- =============================================================== OCCASIONS -->
<?php if (($occasions ?? []) !== []): ?>
    <section class="rs-shell rs-section">
        <?php if ($g('occasions', 'eyebrow') !== ''): ?>
            <p class="rs-kicker"><?= esc($g('occasions', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h2 class="rs-display rs-display--lg mt-5">
            <?= esc($g('occasions', 'title', 'Corporate gifting for every occasion.')) ?>
        </h2>

        <div class="rs-loop rs-loop--gridup mt-10" data-loop="mobile" data-loop-below="768">
            <ul class="rs-loop__track rs-loop__track--tiles" data-loop-track>
                <?php foreach ($occasions as $occasion): ?>
                    <li class="rs-loop__item">
                        <a href="<?= rs_collection_url((string) $occasion['slug']) ?>" class="rs-tile">
                            <img src="<?= rs_url(rs_image($occasion['image'] ?? null, 'products')) ?>" alt=""
                                 class="rs-tile__img" loading="lazy" decoding="async" width="640" height="480">
                            <span class="rs-tile__veil" aria-hidden="true"></span>
                            <span class="rs-tile__body">
                                <span class="rs-tile__name"><?= esc($occasion['name']) ?></span>
                                <span class="rs-tile__go" aria-hidden="true"><?= rs_icon('arrow-right', 'h-4 w-4') ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================ PRODUCT ROWS -->
<?php foreach ($rows ?? [] as $row): ?>
    <?php if ($row['products'] === []) { continue; } ?>
    <section class="rs-shell rs-section pt-0">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="rs-display rs-display--md"><?= esc((string) $row['title']) ?></h2>

            <?php $to = $safe((string) ($row['link'] ?? '')); ?>
            <?php if ($to !== null): ?>
                <a href="<?= esc($to, 'attr') ?>" class="rs-kicker text-mulberry">View all &rarr;</a>
            <?php endif; ?>
        </div>

        <?php /* A GRID, not a rail: on this page the pieces are the point, and
                 a business scanning for what to order should see them all at
                 once rather than swiping through four at a time. */ ?>
        <div class="rs-grid mt-8">
            <?php foreach ($row['products'] as $product): ?>
                <div class="rs-reveal">
                    <?= view('partials/product_card', [
                        'product' => $product,
                        'images'  => $imageMap[(int) $product->id] ?? [],
                    ]) ?>
                </div>
            <?php endforeach; ?>
        </div>

    </section>
<?php endforeach; ?>

<!-- =================================================================== SPLIT -->
<?php
$cards = array_values(array_filter(
    (array) ($d['split']['cards'] ?? []),
    static fn ($c): bool => is_array($c) && trim((string) ($c['label'] ?? '')) !== ''
));
?>
<?php if ($g('split', 'title') !== ''): ?>
    <section class="rs-split">
        <div class="rs-split__panel">
            <?php /* The inner box is what lines the words up with the container;
                     the panel itself keeps the colour full width. */ ?>
            <div class="rs-split__inner">
            <h2 class="rs-display rs-display--lg text-shell"><?= esc($g('split', 'title')) ?></h2>

            <?php if ($cards !== []): ?>
                <ul class="mt-9 grid gap-4 sm:grid-cols-2">
                    <?php foreach ($cards as $card): ?>
                        <?php $to = $safe((string) ($card['link'] ?? '')); ?>
                        <li>
                            <?= $to !== null
                                ? '<a href="' . esc($to, 'attr') . '" class="rs-occard">'
                                : '<span class="rs-occard">' ?>
                                <span class="rs-occard__icon" aria-hidden="true">
                                    <?= rs_icon(trim((string) ($card['icon'] ?? '')) ?: 'gift', 'h-6 w-6') ?>
                                </span>
                                <span class="rs-occard__label"><?= esc((string) $card['label']) ?></span>
                            <?= $to !== null ? '</a>' : '</span>' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            </div>
        </div>

        <?php if ($g('split', 'image') !== ''): ?>
            <div class="rs-split__figure">
                <?= rs_picture($g('split', 'image'), '(min-width: 1024px) 55vw, 100vw', [
                    '_type' => 'hero',
                    'alt'   => '',
                    'class' => 'h-full w-full object-cover',
                ]) ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- ================================================================== INVITE -->
<?php if ($g('invite', 'title') !== ''): ?>
    <?= view('partials/lead_form', [
        'title'     => $g('invite', 'title'),
        'body'      => $g('invite', 'body'),
        'formTitle' => $g('invite', 'form_title', 'Let us craft something special'),
        'source'    => 'corporate',
    ]) ?>
<?php endif; ?>

<?= $this->endSection() ?>
