<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<section class="rs-shell max-w-md py-14 lg:py-20">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
    <p class="rs-eyebrow mt-6">Your account</p>
    <h1 class="mt-4 text-3xl sm:text-4xl">Choose a new password.</h1>
    <p class="mt-3 leading-relaxed text-ink-muted">Pick something you have not used elsewhere.</p>

    <form method="post" action="<?= site_url('account/reset') ?>" class="mt-8 border border-shell-line bg-white p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
        <label class="block">
            <span class="rs-label">New password</span>
            <input type="password" name="password" class="rs-input" required minlength="10"
                   autofocus autocomplete="new-password">
            <span class="rs-help">At least 10 characters.</span>
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Type it again</span>
            <input type="password" name="password_confirm" class="rs-input" required autocomplete="new-password">
        </label>
        <button type="submit" class="rs-btn rs-btn--primary mt-6 w-full">Set new password</button>
    </form>

    <p class="mt-5 text-sm text-ink-muted">Link stopped working? <a href="<?= site_url('account/forgot') ?>" class="rs-link text-mulberry font-medium">Request a new one</a>.</p>
</section>

<?= $this->endSection() ?>
