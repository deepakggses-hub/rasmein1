<?php
/**
 * Flash messages. One place, so every action reports itself the same way.
 * Errors say what went wrong; successes name what happened.
 */
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');

if ($success === null && $error === null && empty($errors)) { return; }
?>
<div class="rs-shell pt-6" role="status" aria-live="polite">
    <?php if ($success !== null): ?>
        <p class="flex items-start gap-3 border-l-2 border-pista-deep bg-pista/10 px-4 py-3 text-sm">
            <span class="rs-badge rs-badge--enquire shrink-0">Done</span>
            <span class="text-ink-soft"><?= esc($success) ?></span>
        </p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="mt-3 flex items-start gap-3 border-l-2 border-bad bg-rose/25 px-4 py-3 text-sm">
            <span class="rs-badge shrink-0 bg-bad text-shell">Problem</span>
            <span class="text-ink-soft"><?= esc($error) ?></span>
        </p>
    <?php endif; ?>

    <?php if (! empty($errors) && is_array($errors)): ?>
        <div class="mt-3 border-l-2 border-bad bg-rose/25 px-4 py-3 text-sm">
            <p class="font-semibold">Please check these:</p>
            <ul class="mt-2 space-y-1">
                <?php foreach ($errors as $message): ?>
                    <li class="flex gap-2 text-ink-soft">
                        <span aria-hidden="true" class="text-bad">&bull;</span>
                        <span><?= esc($message) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
