<?php
/**
 * Shown when a visitor saves something without being signed in.
 *
 * Not a wall: the item HAS been saved against their browser already, so nothing
 * is lost if they dismiss this. It exists because a saved item is the best
 * moment to offer an account — and because a wishlist kept only in a cookie
 * disappears with the cookie.
 *
 * Rendered once per page and hidden; the script shows it.
 */
?>
<div class="rs-modal" data-auth-modal hidden role="dialog" aria-modal="true"
     aria-labelledby="rs-modal-title">
    <div class="rs-modal__card">
        <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-brass-soft text-mulberry">
            <?= rs_icon('heart', 'h-5 w-5') ?>
        </span>

        <h2 id="rs-modal-title" class="rs-display rs-display--md mt-4">Saved.</h2>

        <p class="mt-3 text-sm leading-relaxed text-ink-muted">
            It is kept in this browser. Sign in and we will keep it on your account —
            and it will still be there on your phone.
        </p>

        <div class="mt-6 grid gap-2.5">
            <a href="<?= site_url('account/login') ?>" class="rs-btn rs-btn--primary w-full">Sign in</a>
            <a href="<?= site_url('account/login?mode=register') ?>" class="rs-btn rs-btn--outline w-full">
                Create an account
            </a>
        </div>

        <button type="button" class="rs-link mt-5 text-sm text-ink-muted" data-modal-close>
            Keep browsing
        </button>
    </div>
</div>
