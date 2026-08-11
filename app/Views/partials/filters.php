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

    <?php foreach ($facets as $facet): ?>
        <fieldset class="rs-facet">
            <legend class="rs-facet__head"><?= esc($facet['label']) ?></legend>

            <div class="rs-facet__list">
                <?php foreach ($facet['options'] as $option): ?>
                    <?php if ($facet['type'] === 'link'): ?>
                        <?php /* A category is a place, not a checkbox — it changes
                                 the URL and the breadcrumb rather than adding a
                                 query string. */ ?>
                        <a href="<?= esc($option['url'], 'attr') ?>" class="rs-facet__row">
                            <span class="rs-facet__name"><?= esc($option['label']) ?></span>
                            <span class="rs-facet__count num"><?= (int) $option['count'] ?></span>
                        </a>
                    <?php else: ?>
                        <label class="rs-facet__row">
                            <input type="checkbox"
                                   name="<?= esc($facet['key'], 'attr') ?>[]"
                                   value="<?= esc($option['value'], 'attr') ?>"
                                   <?= ! empty($option['selected']) ? 'checked' : '' ?>>
                            <span class="rs-facet__name"><?= esc($option['label']) ?></span>
                            <span class="rs-facet__count num"><?= (int) $option['count'] ?></span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php /* Shown only when the auto-submit script has not run. */ ?>
    <div class="rs-facet" data-filters-manual>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm w-full">Apply filters</button>
    </div>
</form>
