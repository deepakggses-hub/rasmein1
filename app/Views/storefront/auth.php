<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * Sign in / create account, as one screen with a sliding panel.
 *
 * Both forms are in the markup and CSS moves the panel between them, so the
 * switch is instant and works with no JavaScript — the anchor changes the URL
 * hash, and :target does the rest. The mode also survives a failed submit,
 * because a server-side redirect back sets it explicitly.
 *
 * @var string $mode
 * @var array  $copy
 * @var bool   $google
 */
$c = static fn (string $k, string $fallback = ''): string => trim($copy[$k] ?? '') !== '' ? $copy[$k] : $fallback;
?>

<section class="rs-auth" data-auth data-mode="<?= esc($mode, 'attr') ?>">
    <div class="rs-auth__inner">

        <!-- ======================================================= forms -->
        <div class="rs-auth__forms">

            <!-- ---------------------------------------------- sign in --->
            <div class="rs-auth__pane" data-auth-pane="login">
                <h1 class="rs-display rs-display--md">
                    <?= esc($c('auth_login_title', 'Welcome back.')) ?>
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-ink-muted">
                    <?= esc($c('auth_login_body', 'Enter your email or phone number and we will send you a code.')) ?>
                </p>

                <?php if ($google): ?>
                    <a href="<?= site_url('account/google') ?>" class="rs-googlebtn mt-7">
                        <?= rs_icon('google', 'h-4 w-4') ?>
                        Continue with Google
                    </a>

                    <div class="rs-auth__or"><span>or</span></div>
                <?php elseif ($showGoogleHint): ?>
                    <?php /* Only an administrator sees this. A visitor should
                             never be shown a route that cannot work, but the
                             person setting the shop up needs to know WHY the
                             button is missing rather than assuming it is broken. */ ?>
                    <p class="mt-7 border border-brass bg-brass-soft/20 px-3 py-2 text-xs text-ink-soft">
                        Google sign-in is not set up. Add the client ID and secret under
                        <a href="<?= site_url('admin/settings?group=auth') ?>" class="rs-link text-mulberry">Settings → auth</a>.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= site_url('account/code/request') ?>" class="mt-5 grid gap-4">
                    <?= csrf_field() ?>

                    <label>
                        <span class="rs-label">Email or phone number</span>
                        <input type="text" name="identifier" class="rs-input" required autocomplete="username"
                               placeholder="you@example.com or 98765 43210"
                               value="<?= esc(old('identifier') ?? '', 'attr') ?>">
                        <span class="rs-help">
                            Either works. The code always goes to your email.
                        </span>
                    </label>

                    <button type="submit" class="rs-btn rs-btn--primary w-full">Send me a code</button>
                </form>

                <p class="mt-6 text-sm text-ink-muted">
                    New here?
                    <a href="<?= site_url('account/login?mode=register') ?>" class="rs-link text-mulberry" data-auth-switch="register">Create an account</a>
                </p>
            </div>

            <!-- ------------------------------------------ create account --->
            <div class="rs-auth__pane" data-auth-pane="register" >
                <h1 class="rs-display rs-display--md">
                    <?= esc($c('auth_register_title', 'Join us.')) ?>
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-ink-muted">
                    <?= esc($c('auth_register_body', 'We will email you a code to confirm your address.')) ?>
                </p>

                <?php if ($google): ?>
                    <a href="<?= site_url('account/google') ?>" class="rs-googlebtn mt-7">
                        <?= rs_icon('google', 'h-4 w-4') ?>
                        Continue with Google
                    </a>

                    <div class="rs-auth__or"><span>or</span></div>
                <?php elseif ($showGoogleHint): ?>
                    <?php /* Only an administrator sees this. A visitor should
                             never be shown a route that cannot work, but the
                             person setting the shop up needs to know WHY the
                             button is missing rather than assuming it is broken. */ ?>
                    <p class="mt-7 border border-brass bg-brass-soft/20 px-3 py-2 text-xs text-ink-soft">
                        Google sign-in is not set up. Add the client ID and secret under
                        <a href="<?= site_url('admin/settings?group=auth') ?>" class="rs-link text-mulberry">Settings → auth</a>.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= site_url('account/register') ?>" class="mt-5 grid gap-4">
                    <?= csrf_field() ?>

                    <label>
                        <span class="rs-label">Your name</span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               autocomplete="name" value="<?= esc(old('name') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Email address</span>
                        <input type="email" name="email" class="rs-input" required maxlength="191"
                               autocomplete="email" value="<?= esc(old('email') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Phone number</span>
                        <input type="tel" name="phone" class="rs-input num" required maxlength="20"
                               autocomplete="tel" placeholder="98765 43210"
                               value="<?= esc(old('phone') ?? '', 'attr') ?>">
                        <span class="rs-help">For delivery updates. We will not use it for anything else.</span>
                    </label>

                    <label class="flex items-start gap-2.5 text-sm text-ink-soft">
                        <input type="checkbox" name="marketing" value="1" class="mt-0.5 accent-mulberry">
                        <span>Send me occasional notes about new collections.</span>
                    </label>

                    <button type="submit" class="rs-btn rs-btn--primary w-full">Create my account</button>
                </form>

                <p class="mt-6 text-sm text-ink-muted">
                    Already have an account?
                    <a href="<?= site_url('account/login?mode=login') ?>" class="rs-link text-mulberry" data-auth-switch="login">Sign in</a>
                </p>
            </div>
        </div>

        <!-- ================================================ sliding panel -->
        <?php /* Decorative and duplicated by the links above, so it is hidden
                 from assistive technology rather than read out twice. */ ?>
        <aside class="rs-auth__panel" aria-hidden="true">
            <div class="rs-auth__panelinner">
                <p class="rs-kicker rs-kicker--light"><?= esc($c('auth_panel_eyebrow', 'Rasmein')) ?></p>
                <p class="rs-display rs-display--md mt-5 text-shell">
                    <?= esc($c('auth_panel_title', 'Gifting that carries a feeling.')) ?>
                </p>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-shell/75">
                    <?= esc($c('auth_panel_body', 'Keep your saved pieces, track every order, and reorder a hamper in a tap.')) ?>
                </p>

                <a href="<?= site_url('account/login?mode=register') ?>"
                   class="rs-btn rs-btn--ghost mt-8" data-auth-switch="register" data-panel-cta tabindex="-1">
                    Create an account
                </a>
            </div>
        </aside>
    </div>
</section>

<?= $this->endSection() ?>
