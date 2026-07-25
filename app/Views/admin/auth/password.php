<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php /** @var bool $forced */ ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Your account',
    'heading'    => $forced ? 'Set your own password' : 'Change your password',
    'subheading' => $forced
        ? 'The password you were issued must be replaced before you can continue.'
        : null,
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="post" action="<?= site_url('admin/password') ?>" class="max-w-md border border-shell-line bg-white p-6">
        <?= csrf_field() ?>

        <label class="block">
            <span class="rs-label">Current password</span>
            <input type="password" name="current_password" class="rs-input" required autocomplete="current-password">
        </label>

        <label class="mt-5 block">
            <span class="rs-label">New password</span>
            <input type="password" name="new_password" class="rs-input" required minlength="10"
                   autocomplete="new-password">
            <span class="rs-help">At least 10 characters. Longer beats complicated.</span>
        </label>

        <label class="mt-5 block">
            <span class="rs-label">Type it again</span>
            <input type="password" name="confirm_password" class="rs-input" required autocomplete="new-password">
        </label>

        <button type="submit" class="rs-btn rs-btn--primary mt-7 w-full">Update password</button>
    </form>
</div>

<?= $this->endSection() ?>
