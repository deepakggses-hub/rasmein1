<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<section class="rs-shell max-w-md py-14 lg:py-20">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
    <p class="rs-eyebrow mt-6">Your account</p>
    <h1 class="mt-4 text-3xl sm:text-4xl">Sign in.</h1>
    <p class="mt-3 leading-relaxed text-ink-muted">Your orders, addresses and wishlist, in one place.</p>

    <form method="post" action="<?= site_url('account/login') ?>" class="mt-8 border border-shell-line bg-white p-6">
        <?= csrf_field() ?>
        <label class="block">
            <span class="rs-label">Email</span>
            <input type="email" name="email" class="rs-input" required autocomplete="username"
                   autofocus value="<?= esc(old('email') ?? '', 'attr') ?>">
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Password</span>
            <input type="password" name="password" class="rs-input" required autocomplete="current-password">
        </label>
        <p class="mt-3 text-sm">
            <a href="<?= site_url('account/forgot') ?>" class="rs-link text-ink-muted">Forgotten your password?</a>
        </p>
        <button type="submit" class="rs-btn rs-btn--primary mt-6 w-full">Sign in</button>
    </form>

    <p class="mt-5 text-sm text-ink-muted">New here? <a href="<?= site_url('account/register') ?>" class="rs-link text-mulberry font-medium">Create an account</a>. You can also check out as a guest.</p>
</section>

<?= $this->endSection() ?>
