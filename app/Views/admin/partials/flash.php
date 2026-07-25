<?php
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');
if ($success === null && $error === null && empty($errors)) { return; }
?>
<div class="border-b border-shell-line bg-white px-5 py-3 lg:px-8" role="status" aria-live="polite">
    <?php if ($success !== null): ?>
        <p class="text-sm text-pista-deep"><span class="font-semibold">Done.</span> <?= esc($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="text-sm text-bad"><span class="font-semibold">Problem.</span> <?= esc($error) ?></p>
    <?php endif; ?>
    <?php if (! empty($errors) && is_array($errors)): ?>
        <ul class="text-sm text-bad">
            <?php foreach ($errors as $message): ?>
                <li><?= esc($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
