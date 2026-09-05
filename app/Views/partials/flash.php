<?php
/**
 * Flash messages, as a SweetAlert toast.
 *
 * The markup stays in the page as well, hidden. Two reasons: a reader on a
 * screen reader gets the message announced through `aria-live` whether or not
 * the script runs, and if SweetAlert fails to load the message is still there
 * rather than silently lost.
 *
 * A toast, not a modal. A confirmation the person did not ask for should not
 * make them dismiss a dialogue before they can carry on — only a genuine
 * problem is worth interrupting for.
 */
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');

if ($success === null && $error === null && empty($errors)) {
    return;
}

// Field errors collapse into one message; the form itself marks the fields.
$lines = [];

if (! empty($errors) && is_array($errors)) {
    foreach ($errors as $message) {
        $lines[] = (string) $message;
    }
}

$payload = [
    'success' => $success,
    'error'   => $error !== null ? $error : ($lines !== [] ? implode(' ', $lines) : null),
];
?>

<?php /* The no-JavaScript fallback, and what a screen reader announces. */ ?>
<div class="rs-shell pt-6" role="status" aria-live="polite" data-flash
     data-payload="<?= esc(json_encode($payload), 'attr') ?>">
    <noscript>
        <?php if ($success !== null): ?>
            <p class="flex items-start gap-3 border-l-2 border-pista-deep bg-pista/10 px-4 py-3 text-sm">
                <span class="rs-badge rs-badge--enquire shrink-0">Done</span>
                <span class="text-ink-soft"><?= esc($success) ?></span>
            </p>
        <?php endif; ?>

        <?php if ($payload['error'] !== null): ?>
            <p class="mt-3 flex items-start gap-3 border-l-2 border-bad bg-rose/25 px-4 py-3 text-sm">
                <span class="rs-badge shrink-0 bg-bad text-shell">Problem</span>
                <span class="text-ink-soft"><?= esc($payload['error']) ?></span>
            </p>
        <?php endif; ?>
    </noscript>

    <?php /* Read out, but not drawn — the toast is the visible version. */ ?>
    <p class="sr-only">
        <?= esc((string) ($success ?? $payload['error'] ?? '')) ?>
    </p>
</div>
