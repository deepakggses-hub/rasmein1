<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The story page.
 *
 * Every string comes from the page's `data`, edited in the admin. A section
 * with nothing in it is not drawn — an empty band reads as broken, one fewer
 * section does not.
 *
 * @var array $data
 */
$d = $data ?? [];
$g = static fn (string $s, string $f, string $fb = ''): string => trim((string) ($d[$s][$f] ?? '')) !== ''
    ? (string) $d[$s][$f]
    : $fb;
$rows = static fn (string $s, string $f): array => array_values(array_filter(
    (array) ($d[$s][$f] ?? []),
    static fn ($r): bool => is_array($r) && trim(implode('', array_map('strval', $r))) !== ''
));

/* The second word from the end takes the accent, as on every other heading. */
$split = static function (string $text): array {
    $words = preg_split('/\s+/', trim($text)) ?: [];

    if (count($words) < 3) {
        return [$text, '', ''];
    }

    $accent = array_splice($words, -2, 1);

    return [implode(' ', array_slice($words, 0, -1)), $accent[0], end($words)];
};

/* Roman numerals for the principles — i, ii, iii — as in the design. */
$roman = static function (int $n): string {
    $map = ['i', 'ii', 'iii', 'iv', 'v', 'vi'];

    return $map[$n] ?? (string) ($n + 1);
};
?>

<!-- ==================================================================== HERO -->
<?php [$hHead, $hAccent, $hTail] = $split($g('hero', 'title', 'The house that traditions built.')); ?>
<section class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell rs-section text-center">
        <?php if ($g('hero', 'eyebrow') !== ''): ?>
            <p class="rs-kicker rs-kicker--centred"><?= esc($g('hero', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h1 class="rs-display rs-display--xl mt-5">
            <?= esc($hHead) ?>
            <?php if ($hAccent !== ''): ?><em><?= esc($hAccent) ?></em> <?= esc($hTail) ?><?php endif; ?>
        </h1>

        <?php if ($g('hero', 'intro') !== ''): ?>
            <p class="mx-auto mt-6 max-w-xl text-sm leading-relaxed text-ink-muted">
                <?= esc($g('hero', 'intro')) ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ================================================================== ORIGIN -->
<?php $paras = $rows('origin', 'paragraphs'); ?>
<?php if ($paras !== [] || $g('origin', 'title') !== ''): ?>
    <section class="rs-shell rs-section">
        <div class="grid gap-x-[clamp(2rem,6vw,5rem)] gap-y-8 lg:grid-cols-[1fr_1.6fr]">
            <div>
                <?php if ($g('origin', 'chapter') !== ''): ?>
                    <p class="rs-kicker"><?= esc($g('origin', 'chapter')) ?></p>
                <?php endif; ?>

                <h2 class="rs-display rs-display--lg mt-5"><?= esc($g('origin', 'title', 'The origin.')) ?></h2>
            </div>

            <div class="rs-story">
                <?php foreach ($paras as $i => $para): ?>
                    <?php /* A drop capital on each, as in the design. The letter
                             is aria-hidden and repeated in the text, so a screen
                             reader does not hear it twice. */ ?>
                    <p class="rs-story__p"><?= esc((string) $para['body']) ?></p>

                    <?php if ($i === 1 && $g('origin', 'pullquote') !== ''): ?>
                        <blockquote class="rs-pull">
                            <?= esc($g('origin', 'pullquote')) ?>
                        </blockquote>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php /* If there were fewer than two paragraphs the quote has
                         not been placed yet, so it goes at the end. */ ?>
                <?php if (count($paras) < 2 && $g('origin', 'pullquote') !== ''): ?>
                    <blockquote class="rs-pull"><?= esc($g('origin', 'pullquote')) ?></blockquote>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================================= GALLERY -->
<?php if ($g('gallery', 'image_1') !== '' || $g('gallery', 'image_2') !== ''): ?>
    <section class="rs-shell pb-[clamp(2.25rem,4.2vw,4rem)]">
        <div class="grid gap-4 sm:grid-cols-2">
            <?php foreach ([1, 2] as $n): ?>
                <?php if ($g('gallery', 'image_' . $n) === '') { continue; } ?>
                <figure class="overflow-hidden border border-shell-line">
                    <?= rs_picture($g('gallery', 'image_' . $n), '(min-width: 640px) 45vw, 92vw', [
                        '_type' => 'content',
                        'alt'   => $g('gallery', 'alt_' . $n),
                        'class' => 'w-full object-cover aspect-[4/5]',
                    ]) ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ============================================================== PRINCIPLES -->
<?php $principles = $rows('principles', 'items'); ?>
<?php if ($principles !== []): ?>
    <section class="rs-band">
        <div class="rs-shell rs-section">
            <?php if ($g('principles', 'eyebrow') !== ''): ?>
                <p class="rs-kicker"><?= esc($g('principles', 'eyebrow')) ?></p>
            <?php endif; ?>

            <h2 class="rs-display rs-display--lg mt-5">
                <?= esc($g('principles', 'title', 'Three quiet convictions.')) ?>
            </h2>

            <ol class="mt-10 grid gap-x-[clamp(1.5rem,4vw,3rem)] gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($principles as $i => $item): ?>
                    <li>
                        <p class="rs-numeral"><?= esc($roman($i)) ?>.</p>
                        <h3 class="mt-3 font-display text-xl"><?= esc((string) $item['title']) ?></h3>
                        <p class="mt-3 text-sm leading-relaxed text-ink-muted"><?= esc((string) $item['body']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================================= HISTORY -->
<?php $history = $rows('history', 'items'); ?>
<?php if ($history !== []): ?>
    <section class="rs-shell rs-section">
        <?php if ($g('history', 'eyebrow') !== ''): ?>
            <p class="rs-kicker"><?= esc($g('history', 'eyebrow')) ?></p>
        <?php endif; ?>

        <h2 class="rs-display rs-display--lg mt-5"><?= esc($g('history', 'title', 'A small history.')) ?></h2>

        <ol class="mt-10 border-t border-shell-line">
            <?php foreach ($history as $item): ?>
                <li class="grid gap-2 border-b border-shell-line py-6 sm:grid-cols-[7rem_1fr] lg:grid-cols-[9rem_16rem_1fr] lg:gap-6">
                    <p class="rs-numeral text-lg"><?= esc((string) $item['year']) ?></p>
                    <h3 class="font-display text-lg"><?= esc((string) $item['title']) ?></h3>
                    <p class="text-sm leading-relaxed text-ink-muted sm:col-span-2 lg:col-span-1">
                        <?= esc((string) $item['body']) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

<!-- ================================================================= FOUNDER -->
<?php if ($g('founder', 'quote') !== ''): ?>
    <section class="rs-founder">
        <div class="rs-shell rs-section text-center">
            <?php if ($g('founder', 'eyebrow') !== ''): ?>
                <p class="rs-kicker rs-kicker--light rs-kicker--centred"><?= esc($g('founder', 'eyebrow')) ?></p>
            <?php endif; ?>

            <blockquote class="mx-auto mt-7 max-w-2xl font-display text-[clamp(1.4rem,3vw,2rem)] italic leading-snug text-shell">
                &ldquo;<?= esc($g('founder', 'quote')) ?>&rdquo;
            </blockquote>

            <?php if ($g('founder', 'name') !== ''): ?>
                <p class="mt-7 font-display text-lg italic text-brass">&mdash; <?= esc($g('founder', 'name')) ?></p>
            <?php endif; ?>

            <?php if ($g('founder', 'role') !== ''): ?>
                <p class="mt-2 font-mono text-[0.625rem] tracking-[0.22em] text-shell/60 uppercase">
                    <?= esc($g('founder', 'role')) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ================================================================== INVITE -->
<?php if ($g('invite', 'title') !== ''): ?>
    <?php $wa = preg_replace('/\D/', '', $g('invite', 'whatsapp')) ?? ''; ?>
    <section class="rs-shell rs-section">
        <div class="grid items-center gap-x-[clamp(2rem,6vw,5rem)] gap-y-10 lg:grid-cols-2">
            <div class="text-center lg:text-left">
                <h2 class="rs-display rs-display--lg"><?= esc($g('invite', 'title')) ?></h2>

                <?php if ($g('invite', 'body') !== ''): ?>
                    <p class="mx-auto mt-5 max-w-sm text-sm leading-relaxed text-ink-muted lg:mx-0">
                        <?= esc($g('invite', 'body')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php /* The same enquiry endpoint the rest of the site uses, so a
                     lead from here lands in the same pipeline. */ ?>
            <form method="post" action="<?= site_url('enquiry/submit') ?>"
                  class="border border-shell-line bg-white p-[clamp(1.25rem,3vw,2rem)]">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="about">

                <h3 class="font-display text-xl"><?= esc($g('invite', 'form_title', 'Let us craft something special')) ?></h3>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Name</span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               value="<?= esc(old('name') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Occasion</span>
                        <input type="text" name="occasion" class="rs-input" maxlength="120"
                               value="<?= esc(old('occasion') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Contact</span>
                        <input type="tel" name="phone" class="rs-input num" required maxlength="20"
                               value="<?= esc(old('phone') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Email address</span>
                        <input type="email" name="email" class="rs-input" required maxlength="191"
                               value="<?= esc(old('email') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Estimated no. of gifts</span>
                        <input type="number" name="quantity" class="rs-input num" min="1" max="100000"
                               value="<?= esc(old('quantity') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Budget</span>
                        <input type="text" name="budget" class="rs-input" maxlength="60"
                               value="<?= esc(old('budget') ?? '', 'attr') ?>">
                    </label>

                    <label class="sm:col-span-2">
                        <span class="rs-label">Product brief</span>
                        <textarea name="message" class="rs-textarea" rows="3" maxlength="2000"
                                  placeholder="Message&hellip;"><?= esc(old('message') ?? '') ?></textarea>
                    </label>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Submit product details</button>

                    <?php if ($wa !== ''): ?>
                        <a href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener noreferrer"
                           class="rs-btn rs-btn--outline rs-btn--sm">
                            <?= rs_icon('whatsapp', 'h-4 w-4') ?>
                            Direct message
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<?= $this->endSection() ?>
