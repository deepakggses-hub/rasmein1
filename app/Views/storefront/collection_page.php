<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * A collection landing page.
 *
 * Every section is optional and hides itself when empty, so a shop can fill in
 * as much or as little as it has — a half-configured page should look sparse,
 * never broken.
 *
 * @var array $collection
 * @var array $data
 * @var array $products
 */
$d = $data ?? [];
$g = static fn (string $s, string $f, string $fb = ''): string => trim((string) ($d[$s][$f] ?? '')) !== ''
    ? (string) $d[$s][$f]
    : $fb;
$rows = static fn (string $s, string $f): array => array_values(array_filter(
    (array) ($d[$s][$f] ?? []),
    static fn ($r): bool => is_array($r) && trim(implode('', array_map('strval', $r))) !== ''
));

/* Accent the second word from the end, as the design does. */
$split = static function (string $text): array {
    $w = preg_split('/\s+/', trim($text)) ?: [];

    if (count($w) < 3) {
        return [$text, '', ''];
    }

    $accent = array_splice($w, -2, 1);

    return [implode(' ', array_slice($w, 0, -1)), $accent[0], end($w)];
};

/* A path on this site, or nothing. Never an absolute URL from a setting. */
$safe = static fn (string $p): ?string => str_starts_with($p, '/') ? site_url(ltrim($p, '/')) : null;
?>

<!-- ==================================================================== HERO -->
<?php [$hHead, $hAccent, $hTail] = $split($g('hero', 'title', (string) $collection['name'])); ?>
<section class="rs-collhero">
    <?php if (! empty($collection['hero_image'])): ?>
        <?= rs_picture((string) $collection['hero_image'], '100vw', [
            '_type' => 'hero',
            'alt'   => '',
            'class' => 'rs-collhero__img',
        ]) ?>
    <?php endif; ?>

    <div class="rs-collhero__body">
        <div class="rs-shell">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-xl">
                    <?php if ($g('hero', 'eyebrow') !== ''): ?>
                        <p class="rs-kicker rs-kicker--light"><?= esc($g('hero', 'eyebrow')) ?></p>
                    <?php endif; ?>

                    <h1 class="rs-display rs-display--xl mt-5 text-shell">
                        <?= esc($hHead) ?>
                        <?php if ($hAccent !== ''): ?><em><?= esc($hAccent) ?></em> <?= esc($hTail) ?><?php endif; ?>
                    </h1>

                    <?php if ($g('hero', 'intro') !== ''): ?>
                        <p class="mt-5 max-w-md text-sm leading-relaxed text-shell/80">
                            <?= esc($g('hero', 'intro')) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($g('hero', 'aside') !== ''): ?>
                    <p class="max-w-52 font-display text-sm italic leading-relaxed text-shell/70">
                        &ldquo;<?= esc($g('hero', 'aside')) ?>&rdquo;
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?= view('partials/breadcrumbs', ['crumbs' => $crumbs ?? []]) ?>

<!-- =================================================================== ETHOS -->
<?php $paras = $rows('ethos', 'paragraphs'); ?>
<?php if ($paras !== [] || $g('ethos', 'title') !== ''): ?>
    <section class="rs-shell rs-section">
        <div class="grid gap-x-[clamp(2rem,6vw,5rem)] gap-y-6 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <?php if ($g('ethos', 'eyebrow') !== ''): ?>
                    <p class="rs-kicker"><?= esc($g('ethos', 'eyebrow')) ?></p>
                <?php endif; ?>

                <h2 class="rs-display rs-display--lg mt-5"><?= esc($g('ethos', 'title')) ?></h2>
            </div>

            <div>
                <?php foreach ($paras as $para): ?>
                    <p class="mt-4 text-sm leading-relaxed text-ink-soft first:mt-0">
                        <?= esc((string) $para['body']) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- =================================================================== TILES -->
<?php $tiles = $rows('tiles', 'items'); ?>
<?php if ($tiles !== []): ?>
    <section class="rs-band">
        <div class="rs-shell rs-section">
            <?php if ($g('tiles', 'eyebrow') !== ''): ?>
                <p class="rs-kicker"><?= esc($g('tiles', 'eyebrow')) ?></p>
            <?php endif; ?>

            <h2 class="rs-display rs-display--lg mt-5"><?= esc($g('tiles', 'title')) ?></h2>

            <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($tiles as $i => $tile): ?>
                    <?php $href = $safe(trim((string) ($tile['link'] ?? ''))); ?>
                    <li>
                        <?= $href !== null ? '<a href="' . esc($href, 'attr') . '" class="rs-tile">' : '<span class="rs-tile">' ?>
                            <?php if (! empty($tile['image'])): ?>
                                <?= rs_picture((string) $tile['image'], '(min-width: 1024px) 22vw, 45vw', [
                                    '_type' => 'content',
                                    'alt'   => '',
                                    'class' => 'rs-tile__img',
                                ]) ?>
                            <?php endif; ?>

                            <span class="rs-tile__body">
                                <span class="rs-tile__num"><?= esc(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                <span class="rs-tile__label"><?= esc((string) $tile['label']) ?></span>
                            </span>
                        <?= $href !== null ? '</a>' : '</span>' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- =================================================================== PIECES -->
<?php if (($products ?? []) !== []): ?>
    <section class="rs-shell rs-section">
        <?php if ($g('edit', 'eyebrow') !== ''): ?>
            <p class="rs-kicker"><?= esc($g('edit', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h2 class="rs-display rs-display--lg mt-5">
            <?= esc($g('edit', 'title', $collection['name'] . '.')) ?>
        </h2>

        <a href="<?= site_url('collections/' . $collection['slug']) ?>" class="rs-kicker mt-5 inline-block text-mulberry">
            <?= esc($g('edit', 'link_label', 'View all')) ?> &rarr;
        </a>

        <div class="rs-grid mt-8">
            <?php foreach ($products as $product): ?>
                <div class="rs-reveal">
                    <?= view('partials/product_card', [
                        'product' => $product,
                        'images'  => $imageMap[(int) $product->id] ?? [],
                    ]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================================== FEATURE -->
<?php if ($g('feature', 'title') !== ''): ?>
    <?php [$fHead, $fAccent, $fTail] = $split($g('feature', 'title')); ?>
    <section class="rs-founder">
        <div class="rs-shell rs-section">
            <div class="grid items-center gap-x-[clamp(2rem,6vw,5rem)] gap-y-8 lg:grid-cols-2">
                <?php if ($g('feature', 'image') !== ''): ?>
                    <figure class="overflow-hidden">
                        <?= rs_picture($g('feature', 'image'), '(min-width: 1024px) 45vw, 92vw', [
                            '_type' => 'content',
                            'alt'   => '',
                            'class' => 'w-full object-cover aspect-[4/5]',
                        ]) ?>
                    </figure>
                <?php endif; ?>

                <div>
                    <?php if ($g('feature', 'eyebrow') !== ''): ?>
                        <p class="rs-kicker rs-kicker--light"><?= esc($g('feature', 'eyebrow')) ?></p>
                    <?php endif; ?>

                    <h2 class="rs-display rs-display--lg mt-5 text-shell">
                        <?= esc($fHead) ?>
                        <?php if ($fAccent !== ''): ?><em><?= esc($fAccent) ?></em> <?= esc($fTail) ?><?php endif; ?>
                    </h2>

                    <?php if ($g('feature', 'body') !== ''): ?>
                        <p class="mt-5 max-w-sm text-sm leading-relaxed text-shell/75">
                            <?= esc($g('feature', 'body')) ?>
                        </p>
                    <?php endif; ?>

                    <?php $cta = $safe(trim($g('feature', 'cta_link'))); ?>
                    <?php if ($cta !== null && $g('feature', 'cta_label') !== ''): ?>
                        <a href="<?= esc($cta, 'attr') ?>" class="rs-btn rs-btn--ghost mt-8">
                            <?= esc($g('feature', 'cta_label')) ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- =================================================================== INVITE -->
<?php if ($g('invite', 'title') !== ''): ?>
    <?= view('partials/lead_form', [
        'title'      => $g('invite', 'title'),
        'body'       => $g('invite', 'body'),
        'formTitle'  => $g('invite', 'form_title', 'Let us craft something special'),
        'whatsapp'   => $g('invite', 'whatsapp'),
        'source'     => 'collection:' . $collection['slug'],
    ]) ?>
<?php endif; ?>

<?= $this->endSection() ?>
