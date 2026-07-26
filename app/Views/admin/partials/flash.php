<?php
/**
 * Flash messages.
 *
 * Rendered into the page as usual, AND marked up so admin.js can lift them into
 * toasts. Belt and braces: with JS the toast appears and this block hides; with
 * JS unavailable the block simply stays visible. Neither path loses the message.
 */
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');

if ($success === null && $error === null && empty($errors)) {
    return;
}
?>
<div class="border-b border-shell-line bg-white px-5 py-3 lg:px-8" role="status" aria-live="polite" data-flash>
    <?php if ($success !== null): ?>
        <p class="text-sm text-pista-deep" data-flash-item data-flash-type="success">
            <span class="font-semibold">Done.</span> <?= esc($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="text-sm text-bad" data-flash-item data-flash-type="error">
            <span class="font-semibold">Problem.</span> <?= esc($error) ?>
        </p>
    <?php endif; ?>

    <?php if (! empty($errors) && is_array($errors)): ?>
        <?php foreach ($errors as $message): ?>
            <p class="text-sm text-bad" data-flash-item data-flash-type="error"><?= esc($message) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
