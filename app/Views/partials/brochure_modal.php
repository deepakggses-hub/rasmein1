<?php
/**
 * The brochure lead form, as a modal.
 *
 * ONE per page, not one per button. It is filled from the CTA that was
 * clicked — title, brochure id and where the click came from all ride on
 * `data-` attributes — so a page carrying several brochures still has a single
 * dialogue and a single set of ids.
 *
 * RENDERED HIDDEN, AND ONLY EVER SHOWN BY SCRIPT. With no JavaScript the CTA
 * is a plain link to `/brochure/{id}`, which serves the same form as a real
 * page. This is the enhancement; that is the product.
 *
 * The form has no `action` of its own: the script sets it per brochure, and a
 * form that could submit to the wrong id is worse than one that cannot submit
 * at all without the script that owns it.
 */
?>
<div class="rs-modal" data-brochure-modal hidden role="dialog" aria-modal="true"
     aria-labelledby="rs-brochure-modal-title">
    <div class="rs-modal__card rs-modal__card--form">

        <button type="button" class="rs-modal__x" data-modal-close aria-label="Close">
            <?= rs_icon('close', 'rs-modal__xicon') ?>
        </button>

        <p class="rs-kicker">Brochure</p>
        <h2 id="rs-brochure-modal-title" class="rs-display rs-display--md mt-1" data-brochure-modal-title>
            Download the brochure
        </h2>

        <p class="mt-2 text-sm text-ink-muted">
            Tell us where to send it and the download starts straight away.
        </p>

        <form method="post" class="mt-5 grid gap-3.5 text-left" data-brochure-form novalidate>
            <?= csrf_field() ?>

            <?php /* Off-screen, not display:none — some bots skip a field they can
                     see is hidden. aria-hidden and tabindex keep it away from
                     anyone using the keyboard or a screen reader. */ ?>
            <div class="rs-hp" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <input type="hidden" name="source_type" value="" data-brochure-source>
            <input type="hidden" name="source_id" value="0" data-brochure-source-id>
            <input type="hidden" name="source_url" value="" data-brochure-source-url>

            <label class="block">
                <span class="rs-label">Your name <abbr class="rs-req" title="required">*</abbr></span>
                <input type="text" name="name" class="rs-input" maxlength="191" autocomplete="name" required>
                <span class="rs-fielderr" data-err="name" hidden></span>
            </label>

            <label class="block">
                <span class="rs-label">Phone <abbr class="rs-req" title="required">*</abbr></span>
                <input type="tel" name="phone" class="rs-input" maxlength="40" autocomplete="tel" required>
                <span class="rs-fielderr" data-err="phone" hidden></span>
            </label>

            <label class="block">
                <span class="rs-label">Email <span class="text-ink-muted">(optional)</span></span>
                <input type="email" name="email" class="rs-input" maxlength="191" autocomplete="email">
                <span class="rs-fielderr" data-err="email" hidden></span>
            </label>

            <label class="block">
                <span class="rs-label">Anything we should know? <span class="text-ink-muted">(optional)</span></span>
                <textarea name="notes" rows="2" class="rs-input" maxlength="2000"
                          placeholder="Quantities, timelines, a budget to work to."></textarea>
                <span class="rs-fielderr" data-err="notes" hidden></span>
            </label>

            <p class="rs-fielderr" data-brochure-error hidden></p>

            <button type="submit" class="rs-btn rs-btn--primary mt-1 w-full" data-brochure-submit>
                Download the brochure
            </button>
        </form>
    </div>
</div>
