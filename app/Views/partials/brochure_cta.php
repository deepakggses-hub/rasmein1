<?php
/**
 * The download button a page shows for its brochure.
 *
 * Callers pass `sourceType` and `sourceId` so the lead records which page
 * prompted it. Both keys are passed EXPLICITLY even when empty: CodeIgniter
 * merges the calling view's data into a partial, so `$sourceId ?? null` is not
 * a default — it is whatever the parent happens to have, which is how twelve
 * related product cards once drew the same photograph.
 *
 * Renders nothing when the shop has no brochure at all. "Download our
 * brochure" with nothing behind it is worse than silence.
 *
 * @var array<string, mixed>|null $brochure
 * @var string|null               $sourceType
 * @var int|null                  $sourceId
 */
$brochure = $brochure ?? null;

if ($brochure === null) {
    return;
}

$id   = (int) $brochure['id'];
$size = (int) ($brochure['bytes'] ?? 0);

/*
 * The href is the FORM, not the file. A signed-in customer is redirected
 * straight to the download by the controller, so the same link is one click
 * for them and one form for a guest — and it still works with the script
 * blocked, which a button opening a modal would not.
 */
$href = site_url('brochure/' . $id);
?>
<div class="rs-brochure">
    <span class="rs-brochure__icon" aria-hidden="true"><?= rs_icon('pages', 'rs-brochure__glyph') ?></span>

    <div class="rs-brochure__body">
        <p class="rs-brochure__title"><?= esc($brochure['title']) ?></p>
        <?php if (! empty($brochure['description'])): ?>
            <p class="rs-brochure__note"><?= esc($brochure['description']) ?></p>
        <?php endif; ?>
    </div>

    <?php
    /*
     * Whether this visitor needs the form, decided on the SERVER.
     *
     * A signed-in customer has already given us name, email and phone, so the
     * link simply goes — the controller redirects them to the file. Asking the
     * browser to work this out would mean a round trip before the click could
     * do anything, and guessing it from a cookie would be a second, wronger
     * copy of the same decision.
     */
    $needsForm = session('customer_id') === null;
    ?>
    <a href="<?= $href ?>" class="rs-btn rs-btn--outline rs-btn--sm rs-brochure__cta"
       <?= $needsForm ? 'data-brochure="' . $id . '"' : '' ?>
       data-brochure-title="<?= esc($brochure['title'], 'attr') ?>"
       data-brochure-source="<?= esc((string) ($sourceType ?? ''), 'attr') ?>"
       data-brochure-source-id="<?= (int) ($sourceId ?? 0) ?>">
        Download brochure
        <?php if ($size > 0): ?>
            <span class="rs-brochure__size num">
                <?= $size >= 1048576
                    ? number_format($size / 1048576, 1) . ' MB'
                    : max(1, (int) round($size / 1024)) . ' KB' ?>
            </span>
        <?php endif; ?>
    </a>
</div>
