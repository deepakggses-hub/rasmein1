<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * Enter the code.
 *
 * The address is shown MASKED — enough for the owner to know which inbox to
 * open, not enough to be worth harvesting.
 *
 * @var string $shown
 * @var string $stage
 * @var array  $copy
 */
$c = static fn (string $k, string $fallback = ''): string => trim($copy[$k] ?? '') !== '' ? $copy[$k] : $fallback;
?>

<section class="rs-auth rs-auth--single">
    <div class="rs-auth__card">
        <h1 class="rs-display rs-display--md">
            <?= esc($c('auth_code_title', 'Check your email.')) ?>
        </h1>

        <p class="mt-3 text-sm leading-relaxed text-ink-muted">
            <?= esc($c('auth_code_body', 'We have sent a six-digit code to')) ?>
            <strong class="text-ink"><?= esc($shown) ?></strong>.
        </p>

        <?php /* ============================ DEVELOPMENT ONLY ==============
         *
         * The code on screen, so the sign-in flow can be walked through on a
         * machine with no working SMTP.
         *
         * THREE independent gates, deliberately: OtpService returns the
         * plaintext only when ENVIRONMENT is not production, the controller
         * checks again before flashing it, and this checks a third time before
         * drawing. Any one of them alone would be enough; all three mean a
         * single careless edit cannot put a live code on a customer's screen.
         *
         * It is loud and ugly ON PURPOSE. If this ever appears on the real
         * site it must be unmistakable rather than something that blends in.
         */ ?>
        <?php if (ENVIRONMENT !== 'production' && session('dev_otp')): ?>
            <div class="rs-devcode">
                <p class="rs-devcode__tag">Development only — not shown in production</p>
                <p class="rs-devcode__code num"><?= esc((string) session('dev_otp')) ?></p>
                <p class="rs-devcode__note">
                    Shown because <span class="font-mono">CI_ENVIRONMENT</span> is
                    <span class="font-mono"><?= esc(ENVIRONMENT) ?></span>. The code was still
                    emailed and is still stored hashed.
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('account/code/verify') ?>" class="mt-7 grid gap-4">
            <?= csrf_field() ?>

            <label>
                <span class="sr-only">Your six-digit code</span>
                <?php /* inputmode numeric brings up the number pad on a phone;
                         autocomplete one-time-code lets iOS and Android offer
                         the code straight from the notification. */ ?>
                <input type="text" name="code" class="rs-otp" required
                       inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       autocomplete="one-time-code" autofocus
                       placeholder="000000" data-otp>
            </label>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $stage === 'register' ? 'Create my account' : 'Sign in' ?>
            </button>
        </form>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
            <form method="post" action="<?= site_url('account/code/resend') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="rs-link text-mulberry">Send a new code</button>
            </form>

            <a href="<?= site_url('account/login') ?>" class="rs-link text-ink-muted">Use a different account</a>
        </div>

        <p class="rs-help mt-6">
            The code lasts ten minutes. If it does not arrive, check your spam folder.
        </p>
    </div>
</section>

<?= $this->endSection() ?>
