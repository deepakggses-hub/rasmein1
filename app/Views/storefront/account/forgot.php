<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<section class="rs-shell max-w-md py-14 lg:py-20">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
    <p class="rs-eyebrow mt-6">Your account</p>
    <h1 class="mt-4 text-3xl sm:text-4xl">Reset your password.</h1>
    <p class="mt-3 leading-relaxed text-ink-muted">Tell us your email and we will send a link to set a new password. It lasts one hour.</p>

    <form method="post" action="<?= site_url('account/forgot') ?>" class="mt-8 border border-shell-line bg-white p-6">
        <?= csrf_field() ?>
        <label class="block">
            <span class="rs-label">Email</span>
            <input type="email" name="email" class="rs-input" required autofocus autocomplete="email">
        </label>
        <button type="submit" class="rs-btn rs-btn--primary mt-6 w-full">Send the link</button>
    </form>

    <p class="mt-5 text-sm text-ink-muted">Remembered it? <a href="<?= site_url('account/login') ?>" class="rs-link text-mulberry font-medium">Sign in</a>.</p>
</section>

<?= $this->endSection() ?>
