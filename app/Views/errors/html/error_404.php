<?php
/**
 * 404 — customer-facing error page.
 *
 * CodeIgniter renders error views with a plain include, not through the View
 * service, so this file is deliberately self-contained: no layout, no partials,
 * no model calls. It must render even when the database is down.
 *
 * It must never reveal the framework or its version, the PHP version, a file
 * path, a stack trace, or an exception message.
 */
$brandName = 'Rasmein';
$homeUrl   = function_exists('base_url') ? base_url('/') : '/';
$cssUrl    = function_exists('base_url') ? base_url('assets/css/app.css') : '/assets/css/app.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>We cannot find that page. &middot; <?= $brandName ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Eczar:wght@500;600&family=Karla:wght@400;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $cssUrl ?>">
</head>
<body class="bg-shell text-ink">
    <main class="rs-shell flex min-h-screen max-w-3xl flex-col justify-center py-20">
        <p class="rs-eyebrow">Error 404</p>

        <h1 class="mt-5 font-display text-4xl leading-tight font-semibold sm:text-5xl">
            We cannot find that page.
        </h1>

        <p class="mt-5 max-w-lg text-lg leading-relaxed text-ink-muted">
            The link may be old, or the page may have moved. The shop, the gift-box builder and your account are all still where you left them.
        </p>
<?php
        // Echo back the path they asked for. This comes from the request, not
        // from the exception, so there is no risk of leaking a class name, a
        // file path, or a database message. The framework's own 404 text
        // ("Page Not Found") is deliberately ignored — it only repeats the
        // heading above.
        $asked = function_exists('uri_string') ? '/' . trim((string) uri_string(), '/') : '';
        ?>
        <?php if ($asked !== '' && $asked !== '/' && strlen($asked) <= 120): ?>
            <p class="mt-4 font-mono text-xs tracking-wide text-ink-muted">
                You asked for <?= esc($asked) ?>
            </p>
        <?php endif; ?>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="<?= $homeUrl ?>" class="rs-btn rs-btn--primary">Back to home</a>
            <a href="mailto:hello@rasmein.com" class="rs-btn rs-btn--outline">Tell us about it</a>
        </div>

        <hr class="rs-rule mt-14 max-w-xs">

        <p class="mt-5 font-display text-xl font-semibold text-mulberry">
            Rasme<span class="relative">i<span class="absolute -top-px left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-brass"></span></span>n
        </p>
    </main>
</body>
</html>
