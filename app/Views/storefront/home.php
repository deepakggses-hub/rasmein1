<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>

<?php
/**
 * @var array|null $hero
 * @var array<int, \App\Entities\GiftBox>  $giftBoxes
 * @var array<int, \App\Entities\Product>  $featured
 * @var array<int, \App\Entities\Product>  $newArrivals
 * @var array<int, \App\Entities\Product>  $trayProducts
 * @var array<int, \App\Entities\Category> $categories
 * @var array<int, array<string, mixed>>   $collections
 * @var int  $boxCount
 * @var bool $isEnquire
 */
?>

<!-- ==================================================================
     HERO
     The thesis: gifting here is an act of composition. So the hero is
     the tray itself, mid-assembly, rather than a photograph of a
     finished product. The headline names the act; the tray shows it.
     ================================================================== -->
<section class="relative overflow-hidden bg-mulberry-deep text-shell">
    <!-- Brass rule that anchors the panel to the page below -->
    <div class="absolute inset-x-0 bottom-0 h-px bg-brass/40" aria-hidden="true"></div>

    <div class="rs-shell grid items-center gap-12 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-24">

        <div>
            <p class="rs-eyebrow rs-eyebrow--on-dark">
                <?= esc($hero['eyebrow'] ?? 'Build your own') ?>
            </p>

            <h1 class="mt-5 font-display text-[2.5rem] leading-[1.02] font-semibold sm:text-5xl lg:text-6xl">
                <?php if (! empty($hero['title'])): ?>
                    <?= esc($hero['title']) ?>
                <?php else: ?>
                    Fill the box<br>
                    <span class="text-brass-bright">with the feeling</span><br>
                    you mean.
                <?php endif; ?>
            </h1>

            <p class="mt-6 max-w-md text-base leading-relaxed text-shell/80 sm:text-lg">
                <?= esc($hero['subtitle'] ?? 'Choose a box, fill each compartment yourself, add a note in your own words. Or send one of our ready hampers as it is.') ?>
            </p>

            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="<?= esc($hero['link_url'] ?? site_url('build'), 'attr') ?>" class="rs-btn rs-btn--brass">
                    <?= esc($hero['cta_label'] ?? 'Start a box') ?>
                    <svg class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <path d="M2 7h9M7.5 3.5 11 7l-3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="<?= site_url('gift-boxes') ?>" class="rs-btn rs-btn--on-dark">
                    See ready hampers
                </a>
            </div>

            <!-- Three facts, not three adjectives. -->
            <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-brass/25 pt-6">
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">Boxes</dt>
                    <dd class="num mt-1 font-display text-2xl font-semibold"><?= esc((string) $boxCount) ?></dd>
                </div>
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">Dispatch</dt>
                    <dd class="num mt-1 font-display text-2xl font-semibold">48 hrs</dd>
                </div>
                <div>
                    <dt class="font-mono text-[0.625rem] tracking-[0.16em] text-brass-bright uppercase">Delivery</dt>
                    <dd class="mt-1 font-display text-2xl font-semibold">Pan-India</dd>
                </div>
            </dl>
        </div>

        <!-- The Tray -->
        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
            <?= view('partials/tray', [
                'capacity' => 6,
                'filled'   => array_slice($trayProducts, 0, 4),
                'columns'  => 3,
                'animate'  => true,
            ]) ?>

            <p class="mt-4 flex items-center justify-between gap-4 font-mono text-[0.6875rem] tracking-[0.14em] text-brass-bright uppercase">
                <span>Six compartments</span>
                <span class="num">4 / 6 filled</span>
            </p>
        </div>
    </div>
</section>

<!-- ==================================================================
     HOW IT WORKS — the only place numbering is used, because these
     four steps genuinely are a sequence the customer moves through.
     ================================================================== -->
<section class="rs-shell py-16 lg:py-20">
    <div class="max-w-xl">
        <p class="rs-eyebrow">How a box comes together</p>
        <h2 class="mt-4 text-3xl sm:text-[2.125rem]">Four steps, no guesswork.</h2>
    </div>

    <ol class="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $steps = [
            ['Choose a box', 'Pick a size, a theme, or a budget. The box sets how many compartments you have to play with.'],
            ['Fill it', 'Add products one by one. The tray shows what is left, so you always know where you stand.'],
            ['Make it yours', 'Add a greeting card message and any special request. Both optional, neither an afterthought.'],
            ['Review and send', $isEnquire
                ? 'Check the box over, then send it to us as an enquiry and we will come back with a quote.'
                : 'Check the box over, then check out. We pack it by hand and dispatch within 48 hours.'],
        ];
        foreach ($steps as $index => [$title, $body]):
        ?>
            <li class="relative">
                <span class="num font-mono text-[0.6875rem] tracking-[0.18em] text-brass">
                    <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>
                </span>
                <hr class="rs-rule mt-3">
                <h3 class="mt-4 text-lg font-semibold"><?= esc($title) ?></h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-muted"><?= esc($body) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<!-- ==================================================================
     GIFT BOXES
     ================================================================== -->
<?php if ($giftBoxes !== []): ?>
    <section class="bg-shell-deep py-16 lg:py-20">
        <div class="rs-shell">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-lg">
                    <p class="rs-eyebrow">Start here</p>
                    <h2 class="mt-4 text-3xl sm:text-[2.125rem]">Pick the box, then fill it.</h2>
                </div>
                <a href="<?= site_url('gift-boxes') ?>" class="rs-link font-mono text-[0.6875rem] tracking-[0.16em] text-brass uppercase">
                    All boxes
                </a>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <?php foreach ($giftBoxes as $box): ?>
                    <article class="rs-card flex flex-col overflow-hidden bg-white">
                        <a href="<?= esc($box->builderUrl(), 'attr') ?>" class="block aspect-[3/2] overflow-hidden bg-shell-deep">
                            <img src="<?= esc($box->imageUrl(), 'attr') ?>"
                                 alt="<?= esc($box->name, 'attr') ?>"
                                 loading="lazy" decoding="async"
                                 class="h-full w-full object-cover">
                        </a>

                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex items-center gap-2">
                                <?php if ($box->size_label !== null && $box->size_label !== ''): ?>
                                    <span class="rs-badge rs-badge--soft"><?= esc($box->size_label) ?></span>
                                <?php endif; ?>
                                <span class="rs-badge rs-badge--brass"><?= esc($box->capacityLabel()) ?></span>
                            </div>

                            <h3 class="mt-3 font-display text-xl font-semibold">
                                <a href="<?= esc($box->builderUrl(), 'attr') ?>" class="rs-link"><?= esc($box->name) ?></a>
                            </h3>

                            <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                                <?= esc(rs_excerpt($box->description, 100)) ?>
                            </p>

                            <div class="mt-auto flex items-center justify-between gap-4 pt-6">
                                <p class="text-sm text-ink-muted">
                                    Box
                                    <span class="num font-semibold text-ink"><?= esc($box->formattedBasePrice()) ?></span>
                                    <span class="text-ink-muted">+ contents</span>
                                </p>
                                <a href="<?= esc($box->builderUrl(), 'attr') ?>" class="rs-btn rs-btn--primary rs-btn--sm">
                                    Fill it
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ==================================================================
     CATEGORIES — counts are real, pulled from the catalogue.
     ================================================================== -->
<?php if ($categories !== []): ?>
    <section class="rs-shell py-16 lg:py-20">
        <p class="rs-eyebrow">What goes in</p>
        <h2 class="mt-4 max-w-lg text-3xl sm:text-[2.125rem]">Everything is chosen to sit well beside something else.</h2>

        <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="<?= esc($category->url(), 'attr') ?>"
                       class="rs-card flex items-center gap-5 bg-white p-4">
                        <span class="block h-20 w-20 shrink-0 overflow-hidden bg-shell-deep">
                            <img src="<?= esc($category->imageUrl(), 'attr') ?>"
                                 alt="" loading="lazy" decoding="async"
                                 class="h-full w-full object-cover">
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display text-lg leading-tight font-semibold"><?= esc($category->name) ?></span>
                            <?php if ($category->productCount() !== null): ?>
                                <span class="num mt-1 block font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">
                                    <?= esc((string) $category->productCount()) ?>
                                    <?= $category->productCount() === 1 ? 'product' : 'products' ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <svg class="ml-auto h-4 w-4 shrink-0 text-brass" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 8h8M8 4.5 11.5 8 8 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- ==================================================================
     FEATURED PRODUCTS
     ================================================================== -->
<?php if ($featured !== []): ?>
    <section class="bg-shell-deep py-16 lg:py-20">
        <div class="rs-shell">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-lg">
                    <p class="rs-eyebrow">Favourites</p>
                    <h2 class="mt-4 text-3xl sm:text-[2.125rem]">What people keep reaching for.</h2>
                </div>
                <a href="<?= site_url('shop') ?>" class="rs-link font-mono text-[0.6875rem] tracking-[0.16em] text-brass uppercase">
                    Shop everything
                </a>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($featured as $product): ?>
                    <?= view('partials/product_card', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ==================================================================
     COLLECTIONS
     ================================================================== -->
<?php if ($collections !== []): ?>
    <section class="rs-shell py-16 lg:py-20">
        <p class="rs-eyebrow">Curated</p>
        <h2 class="mt-4 max-w-lg text-3xl sm:text-[2.125rem]">Boxes built for a moment.</h2>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <?php foreach ($collections as $collection): ?>
                <a href="<?= site_url('collections/' . $collection['slug']) ?>"
                   class="group relative block aspect-[4/3] overflow-hidden bg-ink">
                    <img src="<?= esc(rs_image($collection['image'] ?? null, 'products'), 'attr') ?>"
                         alt="<?= esc($collection['name'], 'attr') ?>"
                         loading="lazy" decoding="async"
                         class="h-full w-full object-cover opacity-80 transition duration-500 group-hover:scale-105 group-hover:opacity-70">
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/95 to-transparent p-5">
                        <span class="block font-display text-xl font-semibold text-shell"><?= esc($collection['name']) ?></span>
                        <?php if (! empty($collection['description'])): ?>
                            <span class="mt-1 block text-sm text-shell/75"><?= esc(rs_excerpt($collection['description'], 64)) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ==================================================================
     CLOSING — the copy changes with the journey mode, because what
     happens next genuinely differs.
     ================================================================== -->
<section class="bg-ink py-16 text-shell lg:py-20">
    <div class="rs-shell grid items-center gap-10 lg:grid-cols-[1fr_auto]">
        <div class="max-w-xl">
            <p class="rs-eyebrow rs-eyebrow--on-dark">Gifting at scale</p>
            <h2 class="mt-4 text-3xl sm:text-[2.125rem]">Sending more than a few?</h2>
            <p class="mt-4 text-shell/80">
                Diwali hampers for a team, welcome kits for new joiners, client boxes with your
                own branding. Tell us the brief and the quantity, and we will put together a
                quote with samples.
            </p>
        </div>
        <a href="<?= site_url('page/corporate-gifting') ?>" class="rs-btn rs-btn--brass shrink-0">
            Talk to us about bulk
        </a>
    </div>
</section>

<?= $this->endSection() ?>
