<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The homepage.
 *
 * Built from the supplied design. Conventions that run through it:
 *
 *  - Every section reads its heading from the `home` settings group, so a shop
 *    can rewrite its own copy. A BLANK setting hides the section rather than
 *    rendering an empty band — which is why the values are read raw rather than
 *    through SettingsService::get(), which treats blank as absent.
 *  - A section with nothing to show does not render at all. An empty "best
 *    sellers" rail looks broken; no section at all looks deliberate.
 *  - The closing two words of each display headline are set in gold italic, as
 *    in the design, without asking anyone to write HTML into a settings box.
 *
 * @var array<string, string> $copy
 */
$c = static fn (string $key, string $fallback = ''): string => trim($copy[$key] ?? $fallback);

/** Split a headline so the last two words can carry the accent. */
$split = static function (string $text): array {
    $words = preg_split('/\s+/', trim($text)) ?: [];

    return count($words) >= 4
        ? [implode(' ', array_slice($words, 0, -2)), implode(' ', array_slice($words, -2))]
        : [$text, ''];
};

$heroSlides = $heroSlides ?? [];
$multiSlide = count($heroSlides) > 1;
?>

<!-- ================================================================ HERO -->
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
            <?php [$head, $tail] = $split((string) ($slide['title'] ?? 'Customize your Gift')); ?>
            <div class="rs-hero__slide <?= $index === 0 ? 'is-current' : '' ?>" data-hero-slide
                 <?= $index === 0 ? '' : 'aria-hidden="true"' ?>>
                <?php /* Decorative: everything the image conveys is in the
                         heading beside it, so an empty alt is correct. */ ?>
                <img src="<?= rs_url(rs_image($slide['image'] ?? null, 'banners')) ?>" alt=""
                     class="rs-hero__img"
                     <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                     decoding="async" width="1920" height="900">

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
                    <div class="rs-hero__actions">
                        <a href="<?= rs_url(ltrim((string) ($slide['link_url'] ?: 'shop'), '/')) ?>" class="rs-btn rs-btn--gold">
                            <?= esc($slide['cta_label'] ?: 'Explore collection') ?>
                        </a>
                        <a href="<?= site_url('build') ?>" class="rs-btn rs-btn--ghost">Build your gift box</a>
                    </div>
                </div>
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

<?php
/*
 * The promise strip.
 *
 * Duplicated content plus a -50% translate is what makes the loop seamless
 * with no JavaScript, and aria-hidden on the copy stops a screen reader
 * announcing everything twice.
 */
$rsMarquee = service('design');
?>
<?php if ($rsMarquee->flag('design_marquee')): ?>
    <?php
    $phrases = array_values(array_filter(array_map(
        'trim',
        preg_split('/[·|]/u', $rsMarquee->get('design_marquee_text', '')) ?: []
    )));
    ?>
    <?php if ($phrases !== []): ?>
        <div class="rs-marquee rs-bleed">
            <div class="rs-marquee__track">
                <?php for ($pass = 0; $pass < 2; $pass++): ?>
                    <span class="rs-marquee__item" <?= $pass === 1 ? 'aria-hidden="true"' : '' ?>>
                        <?php foreach ($phrases as $phrase): ?>
                            <span><?= esc($phrase) ?></span>
                            <span class="text-brass" aria-hidden="true">&middot;</span>
                        <?php endforeach; ?>
                    </span>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ========================================================== PHILOSOPHY -->
<?php $philosophy = $c('home_philosophy_title'); ?>
<?php if ($philosophy !== ''): ?>
    <?php [$pHead, $pTail] = $split($philosophy); ?>
    <section class="rs-shell rs-section">
        <div class="grid gap-x-[clamp(2rem,6vw,6rem)] gap-y-8 lg:grid-cols-[1fr_1.15fr] lg:items-start">
            <div class="rs-reveal">
                <p class="rs-kicker"><?= esc($c('home_philosophy_kicker', 'Our philosophy')) ?></p>
                <h2 class="rs-display rs-display--lg mt-5">
                    <?= esc($pHead) ?><?php if ($pTail !== ''): ?> <em><?= esc($pTail) ?></em><?php endif; ?>
                </h2>
            </div>
            <div class="rs-reveal lg:pt-3">
                <?php foreach (preg_split('/\R{2,}/', $c('home_philosophy_body')) ?: [] as $para): ?>
                    <?php if (trim((string) $para) === '') { continue; } ?>
                    <p class="mb-5 text-[1.0625rem] leading-[1.75] text-ink-soft last:mb-0"><?= esc(trim((string) $para)) ?></p>
                <?php endforeach; ?>
                <a href="<?= site_url('page/about') ?>" class="rs-arrowlink mt-7">
                    Our story <?= rs_icon('arrow-right', 'h-4 w-4') ?>
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================ FEATURED COLLECTIONS -->
<?php if ($categories !== []): ?>
    <?php [$cHead, $cTail] = $split($c('home_collections_title', 'A collection for every occasion.')); ?>
    <section class="rs-shell rs-section rs-section--tight">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div>
                <p class="rs-kicker"><?= esc($c('home_collections_kicker', 'Featured collections')) ?></p>
                <h2 class="rs-display rs-display--lg mt-4">
                    <?= esc($cHead) ?><?php if ($cTail !== ''): ?> <em><?= esc($cTail) ?></em><?php endif; ?>
                </h2>
            </div>
            <a href="<?= site_url('collections') ?>" class="rs-arrowlink shrink-0">
                All collections <?= rs_icon('arrow-right', 'h-4 w-4') ?>
            </a>
        </div>

        <ul class="rs-mosaic mt-10">
            <?php foreach ($categories as $category): ?>
                <li class="rs-reveal">
                    <a href="<?= rs_url((string) ($category->path ?? $category->slug)) ?>" class="rs-tile">
                        <img src="<?= rs_url($category->imageUrl()) ?>" alt="" class="rs-tile__img"
                             loading="lazy" decoding="async" width="640" height="480">
                        <span class="rs-tile__veil" aria-hidden="true"></span>
                        <span class="rs-tile__body">
                            <span class="rs-tile__name"><?= esc($category->name) ?></span>
                            <span class="rs-tile__go" aria-hidden="true"><?= rs_icon('arrow-right', 'h-4 w-4') ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- ============================================== THE EDIT — BEST SELLERS -->
<?php if ($featured !== []): ?>
    <?php [$eHead, $eTail] = $split($c('home_edit_title', 'Loved by our patrons.')); ?>
    <section class="rs-band">
        <div class="rs-shell rs-section rs-section--tight">
            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                <div>
                    <p class="rs-kicker"><?= esc($c('home_edit_kicker', 'The edit — best sellers')) ?></p>
                    <h2 class="rs-display rs-display--lg mt-4">
                        <?= esc($eHead) ?><?php if ($eTail !== ''): ?> <em><?= esc($eTail) ?></em><?php endif; ?>
                    </h2>
                </div>
                <?php /* Hidden until the script arms them: arrows that do
                         nothing are worse than no arrows. */ ?>
                <div class="rs-railnav" data-rail-nav hidden>
                    <button type="button" class="rs-railnav__btn" data-rail-prev aria-label="Previous products">
                        <?= rs_icon('arrow-left', 'h-4 w-4') ?>
                    </button>
                    <button type="button" class="rs-railnav__btn" data-rail-next aria-label="More products">
                        <?= rs_icon('arrow-right', 'h-4 w-4') ?>
                    </button>
                </div>
            </div>

            <div class="rs-rail rs-rail--cards mt-9" data-rail>
                <?php foreach ($featured as $product): ?>
                    <div><?= view('partials/product_card', ['product' => $product]) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ===================================================== SHOP BY OCCASION -->
<?php if ($occasions !== []): ?>
    <?php [$oHead, $oTail] = $split($c('home_occasion_title', 'Celebrate every moment worth remembering.')); ?>
    <section class="rs-shell rs-section">
        <div class="mx-auto max-w-2xl text-center">
            <p class="rs-kicker rs-kicker--centred"><?= esc($c('home_occasion_kicker', 'Shop by occasion')) ?></p>
            <h2 class="rs-display rs-display--lg mt-5">
                <?= esc($oHead) ?><?php if ($oTail !== ''): ?> <em><?= esc($oTail) ?></em><?php endif; ?>
            </h2>
        </div>

        <div class="rs-rail rs-rail--occasions mt-10">
            <?php foreach ($occasions as $occasion): ?>
                <a href="<?= rs_url((string) $occasion['slug']) ?>" class="rs-occasion">
                    <img src="<?= rs_url(rs_image($occasion['image'] ?? null, 'products')) ?>" alt=""
                         class="rs-occasion__img" loading="lazy" decoding="async" width="440" height="320">
                    <span class="rs-occasion__label"><?= esc($occasion['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ====================================================== FEATURE BANNER -->
<?php if ($feature !== null): ?>
    <?php [$fHead, $fTail] = $split((string) ($feature['title'] ?? '')); ?>
    <section class="rs-feature">
        <img src="<?= rs_url(rs_image($feature['image'] ?? null, 'banners')) ?>" alt=""
             class="rs-feature__img" loading="lazy" decoding="async" width="1920" height="720">
        <div class="rs-shell rs-feature__inner">
            <h2 class="rs-display rs-display--lg text-shell">
                <em><?= esc($fHead) ?><?= $fTail !== '' ? ' ' . esc($fTail) : '' ?></em>
            </h2>
            <?php if (! empty($feature['subtitle'])): ?>
                <p class="mx-auto mt-5 max-w-xl leading-relaxed text-shell/80"><?= esc($feature['subtitle']) ?></p>
            <?php endif; ?>
            <a href="<?= rs_url(ltrim((string) ($feature['link_url'] ?: 'collections'), '/')) ?>"
               class="rs-btn rs-btn--gold mt-8">
                <?= esc($feature['cta_label'] ?: 'Discover the collection') ?>
            </a>
        </div>
    </section>
<?php endif; ?>

<!-- ======================================================== TESTIMONIALS -->
<?php if ($testimonials !== []): ?>
    <?php [$tHead, $tTail] = $split($c('home_reviews_title', 'Words that warm us.')); ?>
    <section class="rs-shell rs-section">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div>
                <p class="rs-kicker"><?= esc($c('home_reviews_kicker', 'From our patrons')) ?></p>
                <h2 class="rs-display rs-display--lg mt-4">
                    <?= esc($tHead) ?><?php if ($tTail !== ''): ?> <em><?= esc($tTail) ?></em><?php endif; ?>
                </h2>
            </div>
            <?php if ($reviewStats['count'] > 0): ?>
                <p class="num font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                    <?= esc((string) $reviewStats['average']) ?> / 5 &middot;
                    <?= (int) $reviewStats['count'] ?> review<?= $reviewStats['count'] === 1 ? '' : 's' ?>
                </p>
            <?php endif; ?>
        </div>

        <ul class="rs-grid rs-grid--wide mt-10">
            <?php foreach ($testimonials as $quote): ?>
                <li class="rs-quote rs-reveal">
                    <p class="rs-quote__stars" aria-label="<?= (int) $quote['rating'] ?> out of 5">
                        <?php for ($star = 0; $star < (int) $quote['rating']; $star++): ?>
                            <span aria-hidden="true"><?= rs_icon('star', 'h-3.5 w-3.5') ?></span>
                        <?php endfor; ?>
                    </p>
                    <blockquote class="rs-quote__text">&ldquo;<?= esc($quote['quote']) ?>&rdquo;</blockquote>
                    <footer class="rs-quote__who">
                        <span class="rs-quote__avatar" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) $quote['author'], 0, 1))) ?></span>
                        <span>
                            <span class="block text-sm font-medium text-ink"><?= esc($quote['author']) ?></span>
                            <?php if (! empty($quote['role'])): ?>
                                <span class="block text-xs text-ink-muted"><?= esc($quote['role']) ?></span>
                            <?php endif; ?>
                        </span>
                    </footer>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- ====================================================== NOTABLE CLIENTS -->
<?php if ($c('home_clients_title') !== '' && $clients !== []): ?>
    <section class="rs-clients">
        <div class="rs-shell rs-section rs-section--tight text-center">
            <h2 class="font-display text-[clamp(1.15rem,2.2vw,1.75rem)] tracking-[0.16em] text-shell uppercase">
                <?= esc($c('home_clients_title')) ?>
            </h2>
            <ul class="mt-10 grid grid-cols-2 items-center gap-x-8 gap-y-10 sm:grid-cols-3 lg:grid-cols-4">
                <?php foreach ($clients as $client): ?>
                    <li>
                        <?php if (! empty($client['image'])): ?>
                            <img src="<?= rs_url($client['image']) ?>" alt="<?= esc($client['title'] ?? '', 'attr') ?>"
                                 class="mx-auto max-h-12 w-auto object-contain opacity-80 transition-opacity hover:opacity-100"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="font-display text-[clamp(1.15rem,2.4vw,1.9rem)] text-shell/85">
                                <?= esc($client['title'] ?? '') ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- ====================================================== BESPOKE JOURNEY -->
<?php if ($c('home_gallery_title') !== '' && $gallery !== []): ?>
    <section class="rs-shell rs-section">
        <h2 class="rs-display rs-display--md"><?= esc($c('home_gallery_title')) ?></h2>
        <ul class="rs-gallery mt-8">
            <?php foreach ($gallery as $shot): ?>
                <li>
                    <img src="<?= rs_url(rs_image($shot['image'] ?? null, 'banners')) ?>"
                         alt="<?= esc($shot['alt_text'] ?? '', 'attr') ?>"
                         loading="lazy" decoding="async" width="400" height="400">
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- ==================================================== ENQUIRY / SIGN-UP -->
<?php [$sHead, $sTail] = $split($c('home_signup_title', 'Enter our world, unhurried.')); ?>
<section class="rs-band">
    <div class="rs-shell rs-section">
        <div class="grid gap-x-[clamp(2rem,6vw,5rem)] gap-y-10 lg:grid-cols-2 lg:items-center">
            <div class="text-center lg:text-left">
                <?php if (! empty($brand->identity['logo'])): ?>
                    <img src="<?= rs_url($brand->identity['logo']) ?>" alt=""
                         class="rs-logo rs-logo--footer mx-auto lg:mx-0" loading="lazy">
                <?php endif; ?>
                <h2 class="rs-display rs-display--lg mt-6">
                    <?= esc($sHead) ?><?php if ($sTail !== ''): ?> <em><?= esc($sTail) ?></em><?php endif; ?>
                </h2>
                <p class="mx-auto mt-5 max-w-md text-ink-muted lg:mx-0"><?= esc($c('home_signup_body')) ?></p>
            </div>

            <?php /* Posted to the existing enquiry endpoint, so a submission
                     lands in the admin pipeline with every other lead. */ ?>
            <form method="post" action="<?= site_url('enquiry') ?>" class="rs-formcard">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="homepage">
                <h3 class="font-display text-xl">Let&rsquo;s craft something special</h3>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Name <span class="text-bad">*</span></span>
                        <input type="text" name="name" class="rs-input" required maxlength="120" autocomplete="name">
                    </label>
                    <label>
                        <span class="rs-label">Occasion</span>
                        <input type="text" name="occasion" class="rs-input" maxlength="120" placeholder="Wedding, Diwali, corporate…">
                    </label>
                    <label>
                        <span class="rs-label">Contact <span class="text-bad">*</span></span>
                        <input type="tel" name="phone" class="rs-input" required maxlength="40" autocomplete="tel">
                    </label>
                    <label>
                        <span class="rs-label">Email address <span class="text-bad">*</span></span>
                        <input type="email" name="email" class="rs-input" required maxlength="191" autocomplete="email">
                    </label>
                    <label>
                        <span class="rs-label">Estimated no. of gifts</span>
                        <input type="number" name="quantity" class="rs-input num" min="1" max="100000" inputmode="numeric">
                    </label>
                    <label>
                        <span class="rs-label">Budget</span>
                        <input type="text" name="budget" class="rs-input" maxlength="60" placeholder="&#8377; per gift">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Product brief</span>
                        <textarea name="message" class="rs-textarea" rows="3" maxlength="2000"
                                  placeholder="Tell us who it is for and what it should feel like."></textarea>
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rs-btn rs-btn--primary flex-1">Submit project details</button>
                    <?php if (! empty($brand->identity['whatsapp'])): ?>
                        <a href="https://wa.me/<?= esc(preg_replace('/[^0-9]/', '', (string) $brand->identity['whatsapp']), 'attr') ?>"
                           class="rs-btn rs-btn--outline" target="_blank" rel="noopener noreferrer">
                            <?= rs_icon('whatsapp', 'h-4 w-4') ?> Direct message
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
