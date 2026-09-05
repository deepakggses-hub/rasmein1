<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The contact page.
 *
 * Every string here comes from the page's `data`, edited in the admin. Nothing
 * is hard-coded except the shapes — a shop can change all of the words and none
 * of the layout, which is the point of a template.
 *
 * A section with nothing in it is not drawn. An empty band of whitespace where
 * a photograph should be looks broken; one fewer section does not.
 *
 * @var array $d  The page's data, section => field => value
 */
$d = $data ?? [];
$g = static fn (string $s, string $f, string $fb = ''): string => trim((string) ($d[$s][$f] ?? '')) !== ''
    ? (string) $d[$s][$f]
    : $fb;
$rows = static fn (string $s, string $f): array => array_values(array_filter(
    (array) ($d[$s][$f] ?? []),
    static fn ($row): bool => is_array($row) && trim(implode('', array_map('strval', $row))) !== ''
));

/*
 * The last two words of a heading are set in gold italic, exactly as every
 * other display heading on the site does it — so a shop never types HTML into
 * a settings box.
 */
$split = static function (string $text): array {
    $words = preg_split('/\s+/', trim($text)) ?: [];

    if (count($words) < 3) {
        return [$text, ''];
    }

    $tail = array_splice($words, -2);

    return [implode(' ', $words), implode(' ', $tail)];
};
?>

<!-- ==================================================================== HERO -->
<?php [$hHead, $hTail] = $split($g('hero', 'title', 'Write to the atelier.')); ?>
<section class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell rs-section text-center">
        <?php if ($g('hero', 'eyebrow') !== ''): ?>
            <p class="rs-kicker rs-kicker--centred"><?= esc($g('hero', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h1 class="rs-display rs-display--xl mt-5">
            <?= esc($hHead) ?><?php if ($hTail !== ''): ?> <em><?= esc($hTail) ?></em><?php endif; ?>
        </h1>

        <?php if ($g('hero', 'intro') !== ''): ?>
            <p class="mx-auto mt-6 max-w-xl leading-relaxed text-ink-muted">
                <?= esc($g('hero', 'intro')) ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ================================================================ CHANNELS -->
<?php $channels = $rows('channels', 'items'); ?>
<?php if ($channels !== []): ?>
    <section class="rs-shell rs-section">
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($channels as $card): ?>
                <?php
                $link = trim((string) ($card['link'] ?? ''));

                /*
                 * mailto:, tel: and https: are all legitimate here, so the
                 * scheme cannot simply be refused. Anything else — javascript:
                 * above all — is dropped, and a bare path is resolved against
                 * this site.
                 */
                if ($link !== '' && preg_match('#^(mailto:|tel:|https?://|/)#i', $link) !== 1) {
                    $link = '';
                } elseif ($link !== '' && str_starts_with($link, '/')) {
                    $link = site_url(ltrim($link, '/'));
                }
                ?>
                <li class="rs-channel">
                    <span class="rs-channel__icon"><?= rs_icon((string) ($card['icon'] ?? 'mail'), 'h-5 w-5') ?></span>

                    <h2 class="rs-channel__title"><?= esc((string) ($card['title'] ?? '')) ?></h2>

                    <?php if (trim((string) ($card['note'] ?? '')) !== ''): ?>
                        <p class="rs-channel__note"><?= esc((string) $card['note']) ?></p>
                    <?php endif; ?>

                    <?php if (trim((string) ($card['value'] ?? '')) !== ''): ?>
                        <?php if ($link !== ''): ?>
                            <a href="<?= $link ?>" class="rs-channel__value"
                               <?= str_starts_with($link, 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                                <?= esc((string) $card['value']) ?>
                            </a>
                        <?php else: ?>
                            <span class="rs-channel__value"><?= esc((string) $card['value']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- ================================================================== INVITE -->
<?php if ($g('invite', 'title') !== '' || $g('invite', 'address') !== ''): ?>
    <?php [$iHead, $iTail] = $split($g('invite', 'title')); ?>
    <section class="rs-band">
        <div class="rs-shell rs-section">
            <div class="grid gap-x-[clamp(2rem,6vw,5rem)] gap-y-8 lg:grid-cols-2">
                <div>
                    <?php if ($g('invite', 'eyebrow') !== ''): ?>
                        <p class="rs-kicker"><?= esc($g('invite', 'eyebrow')) ?></p>
                    <?php endif; ?>

                    <h2 class="rs-display rs-display--lg mt-5">
                        <?= esc($iHead) ?><?php if ($iTail !== ''): ?> <em><?= esc($iTail) ?></em><?php endif; ?>
                    </h2>

                    <?php if ($g('invite', 'body') !== ''): ?>
                        <p class="mt-5 max-w-md leading-relaxed text-ink-muted"><?= esc($g('invite', 'body')) ?></p>
                    <?php endif; ?>

                    <?php if ($g('invite', 'address') !== ''): ?>
                        <div class="mt-8 border-t border-shell-line pt-7">
                            <p class="rs-kicker"><?= esc($g('invite', 'address_label', 'Our atelier')) ?></p>
                            <?php /* nl2br on escaped text: the shop types an
                                     address across several lines and expects to
                                     see it that way. */ ?>
                            <address class="mt-4 font-display text-lg leading-relaxed not-italic">
                                <?= nl2br(esc($g('invite', 'address'))) ?>
                            </address>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- =================================================================== BAND -->
<?php if ($g('band', 'image') !== ''): ?>
    <section class="rs-contactband">
        <?= rs_picture($g('band', 'image'), '100vw', [
            '_type'   => 'content',
            'alt'     => '',
            'class'   => 'rs-contactband__img',
        ]) ?>

        <?php if ($g('band', 'caption') !== ''): ?>
            <div class="rs-contactband__body">
                <p class="font-display text-[clamp(1.1rem,2vw,1.6rem)] italic text-shell">
                    <?= esc($g('band', 'caption')) ?>
                </p>
                <?php if ($g('band', 'note') !== ''): ?>
                    <p class="mt-2 font-mono text-[0.625rem] tracking-[0.22em] text-shell/70 uppercase">
                        <?= esc($g('band', 'note')) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- ==================================================================== FAQ -->
<?php $faqs = $rows('faq', 'items'); ?>
<?php if ($faqs !== []): ?>
    <section class="rs-shell rs-section">
        <?php if ($g('faq', 'eyebrow') !== ''): ?>
            <p class="rs-kicker"><?= esc($g('faq', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h2 class="rs-display rs-display--lg mt-5"><?= esc($g('faq', 'title', 'Questions we often hear.')) ?></h2>

        <?php /* Native <details>, so a question opens with no JavaScript and is
                 announced correctly. The first is open, as in the design. */ ?>
        <div class="mt-10 border-t border-shell-line">
            <?php foreach ($faqs as $index => $faq): ?>
                <details class="rs-faq" <?= $index === 0 ? 'open' : '' ?>>
                    <summary class="rs-faq__q">
                        <?= esc((string) ($faq['q'] ?? '')) ?>
                        <span class="rs-faq__sign" aria-hidden="true"></span>
                    </summary>
                    <div class="rs-faq__a"><?= nl2br(esc((string) ($faq['a'] ?? ''))) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?= $this->endSection() ?>
