<?php
/**
 * The bulk enquiry dialogue.
 *
 * One modal for the whole site, filled in by whichever button opened it. It
 * posts to the same `enquiry/submit` endpoint the page forms use, so a lead
 * from a product card lands in the same list as one from the story page.
 */
?>
<div class="rs-bulk" data-bulk-modal hidden role="dialog" aria-modal="true" aria-labelledby="rs-bulk-title">
    <div class="rs-bulk__scrim" data-bulk-close></div>

    <div class="rs-bulk__panel">
        <header class="flex items-start justify-between gap-3 border-b border-shell-line px-6 py-5">
            <div>
                <p class="rs-kicker">Bulk enquiry</p>
                <h2 class="mt-2 font-display text-2xl" id="rs-bulk-title">Let us quote for it</h2>
                <?php /* Named, so nobody wonders which piece they are asking
                         about after scrolling a page of them. */ ?>
                <p class="rs-help mt-1" data-bulk-product></p>
            </div>

            <button type="button" class="rs-iconbtn" data-bulk-close aria-label="Close">
                <?= rs_icon('close', 'h-5 w-5') ?>
            </button>
        </header>

        <form method="post" action="<?= site_url('enquiry/submit') ?>" class="px-6 py-5">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="bulk">
            <input type="hidden" name="product_id" data-bulk-product-id value="">

            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Name</span>
                    <input type="text" name="name" class="rs-input" required maxlength="120">
                </label>

                <label>
                    <span class="rs-label">Company</span>
                    <input type="text" name="occasion" class="rs-input" maxlength="120">
                </label>

                <label>
                    <span class="rs-label">Contact</span>
                    <input type="tel" name="phone" class="rs-input num" required maxlength="20">
                </label>

                <label>
                    <span class="rs-label">Email address</span>
                    <input type="email" name="email" class="rs-input" required maxlength="191">
                </label>

                <label>
                    <span class="rs-label">How many</span>
                    <input type="number" name="quantity" class="rs-input num" min="1" max="100000"
                           placeholder="e.g. 200">
                </label>

                <label>
                    <span class="rs-label">Budget</span>
                    <input type="text" name="budget" class="rs-input" maxlength="60">
                </label>

                <label class="sm:col-span-2">
                    <span class="rs-label">Anything else</span>
                    <textarea name="message" class="rs-textarea" rows="3" maxlength="2000"
                              placeholder="Branding, delivery date, packaging&hellip;"></textarea>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Send the enquiry</button>
                <button type="button" class="rs-btn rs-btn--outline rs-btn--sm" data-bulk-close>Cancel</button>
            </div>
        </form>
    </div>
</div>
