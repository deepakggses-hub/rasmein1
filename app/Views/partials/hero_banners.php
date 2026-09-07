<?php
/**
 * The hero banner slider.
 *
 * Shared by the homepage and the corporate page so the two behave identically —
 * a slide that works on one works on the other, and a fix lands in both.
 *
 * @var array<int, array<string, mixed>> $heroSlides
 */
$heroSlides = array_values($heroSlides ?? []);

if ($heroSlides === []) {
    return;
}

$multiSlide = count($heroSlides) > 1;

/* The last two words take the accent, as on every heading in the shop. */
$split = static function (string $text): array {
    $words = preg_split('/\s+/', trim($text)) ?: [];

    return count($words) >= 4
        ? [implode(' ', array_slice($words, 0, -2)), implode(' ', array_slice($words, -2))]
        : [$text, ''];
};
?>
<section class="rs-hero" <?= $multiSlide ? 'data-hero' : '' ?>>
    <?php if ($heroSlides === []): ?>
        <?php /* No banner uploaded yet: a typeset hero rather than a blank
                 rectangle, so a fresh install still looks finished. */ ?>
        <div class="rs-hero__slide rs-hero__slide--bare is-current">
            <div class="rs-shell rs-hero__inner">
                <p class="rs-kicker"><?= esc($brand->brandName) ?></p>
                <h1 class="rs-display rs-display--xl mt-5 text-shell">Customize your <em>Gift</em></h1>
                <p class="rs-hero__lede">
                    Luxury wedding gifts, ritual collections and premium hampers inspired by Indian
                    heritage. Hand-finished, thoughtfully packaged, and made to be remembered.
                </p>
                <div class="rs-hero__actions">
                    <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--gold">Explore collection</a>
                    <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--ghost">Build your gift box</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($heroSlides as $index => $slide): ?>
            <?php
            /*
             * A slide with NO text is treated as finished artwork: the words are
             * already inside the image, so an overlaid heading would repeat them
             * and cover the design. It renders as the picture alone, wrapped in
             * its link. Type anything into title, subtitle or eyebrow and the
             * overlay treatment returns.
             */
            $bare = \App\Models\BannerModel::isBare($slide);
            $link = \App\Models\BannerModel::safeLink($slide);
            [$head, $tail] = $split((string) ($slide['title'] ?? 'Customize your Gift'));
            ?>
            <div class="rs-hero__slide <?= $index === 0 ? 'is-current' : '' ?><?= $bare ? ' rs-hero__slide--bare-art' : '' ?>"
                 data-hero-slide <?= $index === 0 ? '' : 'aria-hidden="true"' ?>>
                <?php
                /*
                 * A decorative image gets alt="" because the heading beside it
                 * already says everything. A BARE slide is the opposite: the
                 * picture is the only content, so it must carry a description —
                 * otherwise the whole hero is silent to a screen reader.
                 */
                $heroImg = '<img src="' . rs_url(rs_image($slide['image'] ?? null, 'banners')) . '"'
                    . ' alt="' . esc($bare ? (string) ($slide['alt_text'] ?? '') : '', 'attr') . '"'
                    . ' class="rs-hero__img"'
                    . ($index === 0 ? ' fetchpriority="high"' : ' loading="lazy"')
                    . ' decoding="async" width="1920" height="900">';
                ?>

                <?php if ($bare): ?>
                    <?php /* The whole picture is the link — a small button on
                             finished artwork looks bolted on. */ ?>
                    <?php if ($link !== null): ?>
                        <a href="<?= $link ?>" class="rs-hero__art"
                           aria-label="<?= esc(($slide['alt_text'] ?? '') ?: 'View this collection', 'attr') ?>">
                            <?= $heroImg ?>
                        </a>
                    <?php else: ?>
                        <?= $heroImg ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?= $heroImg ?>

                <div class="rs-shell rs-hero__inner">
                    <?php if (! empty($slide['eyebrow'])): ?>
                        <p class="rs-kicker"><?= esc($slide['eyebrow']) ?></p>
                    <?php endif; ?>
                    <h1 class="rs-display rs-display--xl mt-5 text-shell">
                        <?= esc($head) ?><?php if ($tail !== ''): ?> <em><?= esc($tail) ?></em><?php endif; ?>
                    </h1>
                    <?php if (! empty($slide['subtitle'])): ?>
                        <p class="rs-hero__lede"><?= esc($slide['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php
                    /*
                     * Both buttons come from the banner. The second used to be
                     * hard-coded to /build, so a shop could not change its
                     * wording, its destination, or remove it. A button with no
                     * label is simply not drawn.
                     */
                    $cta1  = trim((string) ($slide['cta_label'] ?? ''));
                    $cta2  = trim((string) ($slide['cta_label_2'] ?? ''));
                    $link2 = \App\Models\BannerModel::safeLink($slide, 'link_url_2');
                    ?>
                    <?php if ($cta1 !== '' || $cta2 !== ''): ?>
                        <div class="rs-hero__actions">
                            <?php if ($cta1 !== ''): ?>
                                <a href="<?= $link ?? site_url('shop') ?>" class="rs-btn rs-btn--gold">
                                    <?= esc($cta1) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($cta2 !== ''): ?>
                                <a href="<?= $link2 ?? site_url('build') ?>" class="rs-btn rs-btn--ghost">
                                    <?= esc($cta2) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($multiSlide): ?>
            <div class="rs-hero__dots">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <button type="button" class="rs-hero__dot <?= $index === 0 ? 'is-current' : '' ?>"
                            data-hero-dot="<?= $index ?>" aria-label="Slide <?= $index + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
