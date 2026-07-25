<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · Rasmein admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Eczar:wght@500;600&family=Karla:wght@400;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= rs_asset('assets/css/app.css') ?>">
</head>
<body class="bg-mulberry-deep">
    <main class="flex min-h-screen items-center justify-center px-5 py-12">
        <div class="w-full max-w-sm">
            <div class="text-center">
                <span class="font-display text-3xl font-semibold text-shell">
                    Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
                </span>
                <p class="mt-1 font-mono text-[0.625rem] tracking-[0.26em] text-brass uppercase">Admin</p>
            </div>

            <form method="post" action="<?= site_url('admin/login') ?>" class="mt-8 bg-shell p-7">
                <?= csrf_field() ?>

                <?php $error = session()->getFlashdata('error'); ?>
                <?php if ($error !== null): ?>
                    <p class="mb-5 border-l-2 border-bad bg-rose/25 px-3 py-2.5 text-sm text-ink-soft">
                        <?= esc($error) ?>
                    </p>
                <?php endif; ?>

                <?php $success = session()->getFlashdata('success'); ?>
                <?php if ($success !== null): ?>
                    <p class="mb-5 border-l-2 border-pista-deep bg-pista/10 px-3 py-2.5 text-sm text-ink-soft">
                        <?= esc($success) ?>
                    </p>
                <?php endif; ?>

                <label class="block">
                    <span class="rs-label">Email</span>
                    <input type="email" name="email" class="rs-input" required autocomplete="username"
                           autofocus value="<?= esc(old('email') ?? '', 'attr') ?>">
                </label>

                <label class="mt-5 block">
                    <span class="rs-label">Password</span>
                    <input type="password" name="password" class="rs-input" required
                           autocomplete="current-password">
                </label>

                <button type="submit" class="rs-btn rs-btn--primary mt-7 w-full">Sign in</button>
            </form>

            <p class="mt-6 text-center text-xs text-shell/50">
                <a href="<?= site_url('/') ?>" class="rs-link">Back to the storefront</a>
            </p>
        </div>
    </main>
</body>
</html>
