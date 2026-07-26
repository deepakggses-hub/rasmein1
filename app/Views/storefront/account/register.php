<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<section class="rs-shell max-w-md py-14 lg:py-20">
    <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
    <p class="rs-eyebrow mt-6">Your account</p>
    <h1 class="mt-4 text-3xl sm:text-4xl">Create an account.</h1>
    <p class="mt-3 leading-relaxed text-ink-muted">It makes reordering quicker and keeps your addresses to hand. Entirely optional.</p>

    <form method="post" action="<?= site_url('account/register') ?>" class="mt-8 border border-shell-line bg-white p-6">
        <?= csrf_field() ?>
        <?php /* Honeypot. A person never sees this. */ ?>
        <div class="sr-only" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <label class="block">
            <span class="rs-label">Your name</span>
            <input type="text" name="name" class="rs-input" required maxlength="120"
                   autocomplete="name" value="<?= esc(old('name') ?? '', 'attr') ?>">
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Email</span>
            <input type="email" name="email" class="rs-input" required maxlength="191"
                   autocomplete="email" value="<?= esc(old('email') ?? '', 'attr') ?>">
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Phone <span class="text-ink-muted">(optional)</span></span>
            <input type="tel" name="phone" class="rs-input" maxlength="20" autocomplete="tel"
                   value="<?= esc(old('phone') ?? '', 'attr') ?>">
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Password</span>
            <input type="password" name="password" class="rs-input" required minlength="10"
                   autocomplete="new-password">
            <span class="rs-help">At least 10 characters. A few words you will remember beats something clever.</span>
        </label>
        <label class="mt-5 block">
            <span class="rs-label">Type it again</span>
            <input type="password" name="password_confirm" class="rs-input" required autocomplete="new-password">
        </label>
        <label class="mt-5 flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 accent-mulberry">
            <span>Email me occasionally about new boxes and seasonal collections.</span>
        </label>
        <button type="submit" class="rs-btn rs-btn--primary mt-6 w-full">Create account</button>
    </form>

    <p class="mt-5 text-sm text-ink-muted">Already have one? <a href="<?= site_url('account/login') ?>" class="rs-link text-mulberry font-medium">Sign in</a>.</p>
</section>

<?= $this->endSection() ?>
