<?php
/**
 * The filter sidebar.
 *
 * Rendered from whatever FacetService computed for THIS page — see that class
 * for why the facets are derived rather than declared. A facet with one option
 * never reaches here.
 *
 * The whole thing is one GET form. That means the filtered state lives in the
 * URL: it can be shared, bookmarked, and reached by the back button, and it
 * works with JavaScript unavailable. The auto-submit is an enhancement layered
 * on top, and the Apply button is shown when it is not.
 *
 * @var array<int, array<string, mixed>> $facets
 * @var array<string, mixed> $active
 */
$facets = $facets ?? [];

if ($facets === []) {
    return;
}

$q    = $active['q'] ?? null;
$sort = $active['sort'] ?? null;
?>
<form method="get" class="rs-facets" data-filters>
    <?php /* Search and sort survive a filter change: dropping someone's search
             because they ticked a price band is the kind of small betrayal that
             makes people stop using filters. */ ?>
    <?php if ($q !== null && $q !== ''): ?>
        <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
    <?php endif; ?>
    <?php if ($sort !== null && $sort !== ''): ?>
        <input type="hidden" name="sort" value="<?= esc($sort, 'attr') ?>">
    <?php endif; ?>

    <?php foreach ($facets as $index => $facet): ?>
        <?php
        /*
         * Native <details>, so a facet opens and closes with no JavaScript and
         * is announced correctly. The first is open and the rest are closed —
         * five expanded facets push the products off the screen before anyone
         * has chosen anything.
         *
         * A facet with something already ticked opens regardless: a filter that
         * is doing work must not be hidden behind a closed summary, or the
         * result set looks wrong for no visible reason.
         */
        $hasChosen = false;

        foreach ($facet['options'] as $option) {
            if (! empty($option['selected'])) {
                $hasChosen = true;

                break;
            }
        }
        ?>
        <details class="rs-facet" <?= $index === 0 || $hasChosen ? 'open' : '' ?>>
            <summary class="rs-facet__head">
                <?= esc($facet['label']) ?>
                <?php if ($hasChosen): ?>
                    <span class="rs-facet__dot" aria-label="filter applied"></span>
                <?php endif; ?>
                <?php /* Size comes from .rs-facet__chev, not a utility class: a Tailwind
                             class with a dot in it does not survive the escaping rs_icon()
                             applies, and the icon rendered at its intrinsic size. */ ?>
                <?= rs_icon('chevron-down', 'rs-facet__chev') ?>
            </summary>

            <?php if (($facet['type'] ?? '') === 'range'): ?>
                <?php
                /*
                 * Two range inputs stacked on one track. A single input cannot
                 * express a span, and a pair of number boxes makes someone type
                 * where a drag would do.
                 */
                ?>
                <div class="rs-range" data-range
                     data-min="<?= (int) $facet['min'] ?>" data-max="<?= (int) $facet['max'] ?>">
                    <div class="rs-range__track"><span class="rs-range__fill" data-range-fill></span></div>

                    <input type="range" name="min_price" data-range-from
                           min="<?= (int) $facet['min'] ?>" max="<?= (int) $facet['max'] ?>"
                           step="100" value="<?= (int) $facet['from'] ?>"
                           aria-label="Lowest price">
                    <input type="range" name="max_price" data-range-to
                           min="<?= (int) $facet['min'] ?>" max="<?= (int) $facet['max'] ?>"
                           step="100" value="<?= (int) $facet['to'] ?>"
                           aria-label="Highest price">

                    <p class="rs-range__read num">
                        <span data-range-lo><?= rs_money($facet['from']) ?></span>
                        <span aria-hidden="true">&ndash;</span>
                        <span data-range-hi><?= rs_money($facet['to']) ?></span>
                    </p>
                </div>
            <?php else: ?>
            <div class="rs-facet__list">
                <?php foreach ($facet['options'] as $option): ?>
                    <?php if (($facet['type'] ?? '') === 'swatch'): ?>
                        <label class="rs-facet__row">
                            <input type="checkbox" name="<?= esc($facet['key'], 'attr') ?>[]"
                                   value="<?= esc($option['value'], 'attr') ?>"
                                   <?= ! empty($option['selected']) ? 'checked' : '' ?>>
                            <?php if (! empty($option['swatch'])): ?>
                                <span class="rs-facet__swatch"
                                      style="background: <?= esc($option['swatch'], 'attr') ?>"></span>
                            <?php endif; ?>
                            <span class="rs-facet__name"><?= esc($option['label']) ?></span>
                            <span class="rs-facet__count num"
                                  data-facet-count="<?= esc($facet['key'] . ':' . $option['value'], 'attr') ?>"><?= (int) $option['count'] ?></span>
                        </label>
                    <?php elseif ($facet['type'] === 'link' && ! empty($option['url'])): ?>
                        <?php /* A category is a place, not a checkbox — it changes
                                 the URL and the breadcrumb rather than adding a
                                 query string. */ ?>
                        <a href="<?= esc($option['url'], 'attr') ?>"
                           class="rs-facet__row <?= ! empty($option['selected']) ? 'is-current' : '' ?>"
                           <?= ! empty($option['selected']) ? 'aria-current="page"' : '' ?>>
                            <span class="rs-facet__name"><?= esc($option['label']) ?></span>
                            <span class="rs-facet__count num" data-facet-count="<?= esc($facet['key'] . ':' . $option['value'], 'attr') ?>"><?= (int) $option['count'] ?></span>
                        </a>
                    <?php else: ?>
                        <label class="rs-facet__row">
                            <input type="checkbox"
                                   name="<?= esc($facet['key'], 'attr') ?>[]"
                                   value="<?= esc($option['value'], 'attr') ?>"
                                   <?= ! empty($option['selected']) ? 'checked' : '' ?>>
                            <span class="rs-facet__name"><?= esc($option['label']) ?></span>
                            <span class="rs-facet__count num" data-facet-count="<?= esc($facet['key'] . ':' . $option['value'], 'attr') ?>"><?= (int) $option['count'] ?></span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>

    <?php /* Shown only when the auto-submit script has not run. */ ?>
    <div class="rs-facet" data-filters-manual>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm w-full">Apply filters</button>
    </div>
</form>
