<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The short form a Google sign-up lands on.
 *
 * The name arrives from Google and is editable, because the name on someone's
 * Google account is often not the name they want on a gift.
 *
 * @var array $customer
 * @var array $copy
 */
$c = static fn (string $k, string $fallback = ''): string => trim($copy[$k] ?? '') !== '' ? $copy[$k] : $fallback;
?>

<section class="rs-auth rs-auth--single">
    <div class="rs-auth__card">
        <h1 class="rs-display rs-display--md">
            <?= esc($c('auth_details_title', 'Just one more thing.')) ?>
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-ink-muted">
            <?= esc($c('auth_details_body', 'We need a number for delivery updates. Change your name here if you would rather we used another.')) ?>
        </p>

        <form method="post" action="<?= site_url('account/finish') ?>" class="mt-7 grid gap-4">
            <?= csrf_field() ?>

            <label>
                <span class="rs-label">Your name</span>
                <input type="text" name="name" class="rs-input" required maxlength="120" autocomplete="name"
                       value="<?= esc(old('name') ?? $customer['name'] ?? '', 'attr') ?>">
            </label>

            <label>
                <span class="rs-label">Email address</span>
                <?php /* Read-only: Google has verified it, and letting it be
                         edited here would undo that proof. */ ?>
                <input type="email" class="rs-input bg-shell-deep" value="<?= esc($customer['email'], 'attr') ?>"
                       readonly tabindex="-1">
                <span class="rs-help">Confirmed by Google.</span>
            </label>

            <label>
                <span class="rs-label">Phone number</span>
                <input type="tel" name="phone" class="rs-input num" required maxlength="20"
                       autocomplete="tel" placeholder="98765 43210" autofocus
                       value="<?= esc(old('phone') ?? $customer['phone'] ?? '', 'attr') ?>">
            </label>

            <button type="submit" class="rs-btn rs-btn--primary w-full">Finish</button>
        </form>
    </div>
</section>

<?= $this->endSection() ?>
