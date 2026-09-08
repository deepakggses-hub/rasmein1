<?php
/**
 * The media library picker.
 *
 * One modal for the whole admin. Any file input marked `data-media` gets a
 * "Choose from library" button beside it; this dialogue is what opens.
 *
 * Two ways in, as asked: browse what is already uploaded, or send a new file —
 * and a new file lands in the library too, so the next screen can reuse it.
 */
?>
<div class="rs-media" data-media-modal hidden role="dialog" aria-modal="true" aria-labelledby="rs-media-title">
    <div class="rs-media__scrim" data-media-close></div>

    <div class="rs-media__panel">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-shell-line px-5 py-4">
            <h2 class="font-display text-xl" id="rs-media-title">Media library</h2>

            <div class="flex items-center gap-2">
                <?php /* The upload sits in the header, not behind a tab: sending
                         a new file is half of what this dialogue is for, and a
                         tab would hide it behind a click. */ ?>
                <label class="rs-btn rs-btn--outline rs-btn--sm cursor-pointer">
                    Upload from this computer
                    <input type="file" class="sr-only" data-media-upload
                           accept="image/jpeg,image/png,image/webp,image/avif">
                </label>

                <button type="button" class="rs-iconbtn" data-media-close aria-label="Close">
                    <?= rs_icon('close', 'h-5 w-5') ?>
                </button>
            </div>
        </header>

        <div class="border-b border-shell-line px-5 py-3">
            <label class="block">
                <span class="sr-only">Search the library</span>
                <input type="search" class="rs-input" data-media-search
                       placeholder="Search by file name or by what the picture shows&hellip;">
            </label>
            <p class="rs-help mt-2" data-media-count></p>
        </div>

        <div class="rs-media__grid" data-media-grid>
            <p class="rs-help p-5">Loading&hellip;</p>
        </div>

        <footer class="flex items-center justify-between gap-3 border-t border-shell-line px-5 py-4">
            <p class="rs-help" data-media-chosen>Nothing selected.</p>

            <div class="flex gap-2">
                <button type="button" class="rs-btn rs-btn--outline rs-btn--sm" data-media-close>Cancel</button>
                <button type="button" class="rs-btn rs-btn--primary rs-btn--sm" data-media-use disabled>
                    Use this picture
                </button>
            </div>
        </footer>
    </div>
</div>
