<?php
/**
 * THE TRAY — Rasmein's signature element.
 *
 * A top-down view of a compartmented gift tray. One cell per slot of box
 * capacity; filled cells show the actual product. It appears here as a
 * preview and becomes the live capacity indicator inside the builder,
 * so the customer learns one visual idea and sees it again where it counts.
 *
 * @var int   $capacity  Total compartments
 * @var array $filled    Products occupying the first cells (each has imageUrl()/name)
 * @var int   $columns
 * @var bool  $animate
 */
$capacity = max(1, (int) ($capacity ?? 6));
$filled   = $filled   ?? [];
$columns  = (int) ($columns  ?? 3);
$animate  = (bool) ($animate ?? false);
$filledCount = min(count($filled), $capacity);

// Live mode (the builder): each occupied compartment names what is in it and
// how many compartments that item takes, so the cost of a choice is visible.
$live = (bool) ($live ?? false);

$gridClass = match ($columns) {
    2       => 'grid-cols-2',
    4       => 'grid-cols-4',
    default => 'grid-cols-3',
};
?>
<div class="rs-tray <?= $gridClass ?> <?= $animate ? 'rs-tray--animate' : '' ?>"
     role="img"
     aria-label="A gift tray with <?= $capacity ?> compartments, <?= $filledCount ?> filled">
    <?php for ($i = 0; $i < $capacity; $i++): ?>
        <?php $product = $filled[$i] ?? null; ?>
        <?php if ($product !== null): ?>
            <div class="rs-slot rs-slot--filled" <?= $live ? 'title="' . esc($product['label'] ?? '', 'attr') . '"' : '' ?>>
                <?php if ($live): ?>
                    <img src="<?= esc($product['image'] ?? '', 'attr') ?>"
                         alt="<?= esc($product['label'] ?? '', 'attr') ?>"
                         loading="lazy" decoding="async" width="200" height="200">
                <?php else: ?>
                    <img src="<?= esc($product->imageUrl(), 'attr') ?>"
                         alt="" loading="lazy" decoding="async" width="200" height="200">
                <?php endif; ?>
            </div>
        <?php elseif ($i === $filledCount): ?>
            <div class="rs-slot rs-slot--next">
                <span class="rs-slot__mark" aria-hidden="true">+</span>
            </div>
        <?php else: ?>
            <div class="rs-slot">
                <span class="rs-slot__mark" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            </div>
        <?php endif; ?>
    <?php endfor; ?>
</div>
