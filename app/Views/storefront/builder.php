<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * The builder. Steps 2, 3 and 4 on one page, because they are not really
 * separate destinations — you fill, you write a note, you decide how many.
 * Splitting them across three page loads would only add friction.
 *
 * @var array<string, mixed> $state
 */
$box       = $state['box'];
$capacity  = (int) $state['capacity'];
$used      = (int) $state['slots_used'];
$free      = (int) $state['slots_free'];
$minimum   = (int) $state['min_slots'];
$lineId    = (int) $state['line_id'];
$isEnquiry = $box->isEnquireOnly() || rs_is_enquire_mode();

// Expand contents into one entry per occupied compartment, so the Tray shows
// that a platter costs three slots rather than pretending everything costs one.
$occupancy = [];

foreach ($state['components'] as $component) {
    $slotCost = max(1, (int) ($component['giftbox_slots'] ?? 1));
    $label    = $component['product_name'] . ' — ' . $slotCost
        . ' compartment' . ($slotCost === 1 ? '' : 's');

    for ($i = 0, $n = $slotCost * max(1, (int) $component['quantity']); $i < $n; $i++) {
        $occupancy[] = [
            'label' => $label,
            'image' => rs_image($component['product_image'] ?? null, 'products'),
        ];
    }
}
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-8">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>

        <!-- Numbered because these four steps genuinely are a sequence. -->
        <ol class="mt-6 flex flex-wrap gap-x-6 gap-y-2 font-mono text-[0.625rem] tracking-[0.16em] uppercase">
            <?php
            $steps = ['Choose a box', 'Fill it', 'Personalise', 'Review'];
            $currentStep = $used === 0 ? 2 : ($state['is_complete'] ? 4 : 2);
            foreach ($steps as $index => $label):
                $number = $index + 1;
                $done   = $number < $currentStep || ($number === 1);
                $active = $number === $currentStep;
            ?>
                <li class="flex items-center gap-2 <?= $active ? 'text-mulberry' : ($done ? 'text-brass' : 'text-ink-muted') ?>">
                    <span class="num"><?= str_pad((string) $number, 2, '0', STR_PAD_LEFT) ?></span>
                    <span><?= esc($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</header>

<div class="rs-shell py-8 lg:py-12">
    <div class="lg:grid lg:grid-cols-[1fr_23rem] lg:gap-12 lg:items-start">

        <!-- =========================================== the picker ======= -->
        <section>
            <p class="rs-eyebrow">Step two &mdash; fill it</p>
            <h1 class="mt-3 text-3xl sm:text-4xl"><?= esc($box->name) ?></h1>
            <p class="mt-3 max-w-xl leading-relaxed text-ink-muted">
                <?php if ($free === 0): ?>
                    Every compartment is taken. Remove something to swap it out.
                <?php elseif ($used === 0): ?>
                    <?= $capacity ?> compartments to fill. Some items take more than one —
                    the tray shows you as you go.
                <?php else: ?>
                    <span class="num font-semibold text-ink"><?= $free ?></span>
                    compartment<?= $free === 1 ? '' : 's' ?> left.
                <?php endif; ?>
            </p>

            <?php if ($state['catalogue'] === []): ?>
                <p class="mt-8 text-ink-muted">Nothing is available for this box at the moment.</p>
            <?php endif; ?>

            <?php foreach ($state['catalogue'] as $group): ?>
                <div class="mt-10">
                    <h2 class="rs-eyebrow"><?= esc($group['category']) ?></h2>

                    <ul class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($group['products'] as $product): ?>
                            <?php
                            $inBox    = $state['chosen'][$product->id] ?? 0;
                            $slotCost = max(1, (int) $product->giftbox_slots);
                            $fits     = $slotCost <= $free;
                            ?>
                            <li class="rs-card flex gap-4 bg-white p-3 <?= $inBox > 0 ? 'border-brass' : '' ?>">
                                <div class="h-20 w-16 shrink-0 overflow-hidden bg-shell-deep">
                                    <img src="<?= esc($product->imageUrl(), 'attr') ?>" alt=""
                                         loading="lazy" class="h-full w-full object-cover">
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col">
                                    <p class="text-sm leading-snug font-semibold"><?= esc($product->name) ?></p>
                                    <p class="num mt-1 text-sm text-mulberry font-semibold">
                                        <?= esc($product->formattedPrice()) ?>
                                    </p>
                                    <p class="num mt-0.5 font-mono text-[0.5625rem] tracking-[0.12em] text-ink-muted uppercase">
                                        <?= $slotCost ?> slot<?= $slotCost === 1 ? '' : 's' ?>
                                        <?php if (! $product->inStock()): ?>
                                            &middot; <span class="text-bad">sold out</span>
                                        <?php endif; ?>
                                    </p>

                                    <div class="mt-auto pt-2">
                                        <?php if ($inBox > 0): ?>
                                            <div class="flex items-center gap-1.5">
                                                <form method="post" action="<?= site_url('build/box/' . $lineId . '/quantity') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                                                    <input type="hidden" name="quantity" value="<?= $inBox - 1 ?>">
                                                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm px-2.5"
                                                            aria-label="One fewer <?= esc($product->name, 'attr') ?>">&minus;</button>
                                                </form>
                                                <span class="num min-w-7 text-center text-sm font-semibold"><?= $inBox ?></span>
                                                <form method="post" action="<?= site_url('build/box/' . $lineId . '/add') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm px-2.5"
                                                            <?= $fits && $product->inStock() ? '' : 'disabled' ?>
                                                            aria-label="One more <?= esc($product->name, 'attr') ?>">+</button>
                                                </form>
                                            </div>
                                        <?php elseif (! $product->inStock()): ?>
                                            <span class="rs-btn rs-btn--outline rs-btn--sm w-full" aria-disabled="true">Sold out</span>
                                        <?php elseif (! $fits): ?>
                                            <span class="rs-btn rs-btn--outline rs-btn--sm w-full" aria-disabled="true"
                                                  title="Needs <?= $slotCost ?> compartments, <?= $free ?> left">
                                                No room
                                            </span>
                                        <?php else: ?>
                                            <form method="post" action="<?= site_url('build/box/' . $lineId . '/add') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm w-full">Add</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- =========================================== the tray ========== -->
        <aside class="mt-12 lg:mt-0 lg:sticky lg:top-28">

            <div class="border border-shell-line bg-white p-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Your box</h2>
                    <p class="num font-mono text-[0.6875rem] tracking-[0.14em] <?= $used > 0 ? 'text-brass' : 'text-ink-muted' ?>">
                        <?= $used ?> / <?= $capacity ?>
                    </p>
                </div>

                <!-- THE TRAY, live. Filled from the actual contents. -->
                <div class="mt-4">
                    <?= view('partials/tray', [
                        'capacity' => $capacity,
                        'filled'   => $occupancy,
                        'columns'  => $capacity <= 4 ? 2 : ($capacity > 9 ? 4 : 3),
                        'live'     => true,
                        'animate'  => false,
                    ]) ?>
                </div>

                <?php if ($minimum > 0 && $used < $minimum): ?>
                    <p class="mt-4 text-xs text-warn">
                        Fill at least <span class="num font-semibold"><?= $minimum ?></span>
                        compartments to send this box.
                    </p>
                <?php endif; ?>

                <!-- contents list -->
                <?php if ($state['components'] !== []): ?>
                    <ul class="mt-5 space-y-2 border-t border-shell-line pt-4 text-sm">
                        <?php foreach ($state['components'] as $component): ?>
                            <li class="num flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <?= esc(rs_excerpt($component['product_name'], 26)) ?>
                                    <?php if ((int) $component['quantity'] > 1): ?>
                                        <span class="text-ink-muted">&times; <?= (int) $component['quantity'] ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span><?= rs_money((float) $component['product_price'] * (int) $component['quantity']) ?></span>
                                    <form method="post" action="<?= site_url('build/box/' . $lineId . '/remove') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $component['product_id'] ?>">
                                        <button type="submit" class="text-ink-muted hover:text-bad"
                                                aria-label="Remove <?= esc($component['product_name'], 'attr') ?>">&times;</button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <dl class="num mt-4 space-y-1.5 border-t border-shell-line pt-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Contents</dt>
                            <dd><?= rs_money($state['contents_total']) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Box &amp; packing</dt>
                            <dd><?= esc($box->formattedBasePrice()) ?></dd>
                        </div>
                    </dl>
                <?php else: ?>
                    <p class="mt-5 border-t border-shell-line pt-4 text-sm text-ink-muted">
                        Nothing in it yet. Add something from the left.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ============ step 3: personalise (both optional) ========== -->
            <?php if ($box->allow_gift_message || $box->allow_special_note): ?>
                <form method="post" action="<?= site_url('build/box/' . $lineId . '/personalise') ?>"
                      class="mt-5 border border-shell-line bg-white p-5">
                    <?= csrf_field() ?>
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Personalise</h2>
                    <p class="rs-help mt-2">Both optional. Nothing is printed unless you write it.</p>

                    <?php if ($box->allow_gift_message): ?>
                        <label class="mt-4 block">
                            <span class="rs-label">Who is it for?</span>
                            <input type="text" name="gift_recipient" class="rs-input" maxlength="120"
                                   value="<?= esc($state['line']['gift_recipient'] ?? '', 'attr') ?>">
                        </label>
                        <label class="mt-4 block">
                            <span class="rs-label">Message on the card</span>
                            <textarea name="gift_message" class="rs-textarea" rows="3"
                                      maxlength="<?= (int) $box->gift_message_max_chars ?>"><?= esc($state['line']['gift_message'] ?? '') ?></textarea>
                            <span class="rs-help">Up to <?= (int) $box->gift_message_max_chars ?> characters.</span>
                        </label>
                    <?php endif; ?>

                    <?php if ($box->allow_special_note): ?>
                        <label class="mt-4 block">
                            <span class="rs-label">Special request</span>
                            <textarea name="special_note" class="rs-textarea" rows="2" maxlength="500"
                                      placeholder="Allergies, no nuts, deliver on a date…"><?= esc($state['line']['special_note'] ?? '') ?></textarea>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm mt-4 w-full">Save note</button>
                </form>
            <?php endif; ?>

            <!-- =================== step 4: review & proceed ============== -->
            <form method="post" action="<?= site_url('build/box/' . $lineId . '/finish') ?>"
                  class="mt-5 border border-shell-line bg-white p-5">
                <?= csrf_field() ?>
                <h2 class="rs-eyebrow rs-eyebrow--plain">Review</h2>

                <label class="mt-4 flex items-center justify-between gap-3">
                    <span class="rs-label mb-0">How many of this box?</span>
                    <input type="number" name="quantity" class="rs-input num w-20 py-1.5 text-center"
                           value="<?= (int) ($state['line']['quantity'] ?? 1) ?>" min="1" max="99" inputmode="numeric">
                </label>

                <?php if ($isEnquiry): ?>
                    <p class="mt-4 flex gap-2.5 border border-pista/40 bg-pista/10 p-3 text-xs">
                        <span class="rs-badge rs-badge--enquire shrink-0">Quoted</span>
                        <span class="text-ink-soft">This box is priced per brief. We will send a written quote.</span>
                    </p>
                <?php endif; ?>

                <button type="submit" class="rs-btn rs-btn--primary mt-4 w-full"
                        <?= $state['is_complete'] ? '' : 'aria-disabled="true"' ?>>
                    <?= $isEnquiry ? 'Add and enquire' : 'Add to ' . rs_cta_label(null, 'cart') ?>
                </button>

                <?php if (! $state['is_complete']): ?>
                    <p class="rs-help mt-2 text-center">
                        <?= $used === 0 ? 'Add something first.' : 'Needs ' . ($minimum - $used) . ' more.' ?>
                    </p>
                <?php endif; ?>
            </form>

            <div class="mt-4 flex flex-wrap justify-between gap-3 text-sm">
                <?php if ($state['components'] !== []): ?>
                    <form method="post" action="<?= site_url('build/box/' . $lineId . '/clear') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="rs-link text-ink-muted hover:text-bad">Empty the box</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= site_url('build/box/' . $lineId . '/discard') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-link text-ink-muted hover:text-bad">Start over</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<?= $this->endSection() ?>
