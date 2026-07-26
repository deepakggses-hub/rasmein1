<?php
/**
 * A chart, with its data alongside.
 *
 * The payload goes in a <script type="application/json"> block, which the
 * browser never executes — so no inline JavaScript is needed and the page stays
 * ready for the Content Security Policy. The JSON_HEX_* flags matter: without
 * them a product named "</script>" would close the block and the rest would be
 * parsed as markup.
 *
 * @var string $kind    revenue | doughnut | ranked
 * @var array  $labels
 * @var array  $values
 * @var string $title
 * @var string $height  Tailwind height class
 * @var bool   $money   Format tooltips as currency
 * @var string $empty   Message when there is nothing to draw
 */
$kind   = $kind   ?? 'doughnut';
$labels = array_values($labels ?? []);
$values = array_values($values ?? []);
$height = $height ?? 'h-64';
$empty  = $empty  ?? 'Nothing to show for this period yet.';
$id     = 'chart-' . bin2hex(random_bytes(4));
?>
<div class="relative <?= esc($height, 'attr') ?>">
    <canvas id="<?= $id ?>" data-chart="<?= esc($kind, 'attr') ?>"
            role="img"
            aria-label="<?= esc(($title ?? 'Chart') . ': ' . implode(', ', array_map(
                static fn ($l, $v): string => $l . ' ' . $v,
                $labels,
                $values
            )), 'attr') ?>"></canvas>

    <script type="application/json"><?= json_encode([
        'labels' => $labels,
        'values' => $values,
        'money'  => (bool) ($money ?? false),
        'label'  => $title ?? '',
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <p class="absolute inset-0 flex items-center justify-center text-sm text-ink-muted"
       data-chart-empty <?= $labels === [] ? '' : 'hidden' ?>>
        <?= esc($empty) ?>
    </p>
</div>
