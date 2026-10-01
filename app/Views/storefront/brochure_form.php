<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The details we ask a guest for before a brochure downloads.
 *
 * A real PAGE, not only a modal. The modal is the pleasant path when the
 * script is running; this is what the link points at, so it still works with
 * JavaScript blocked, in a new tab, and when somebody shares the URL.
 *
 * @var array<string, mixed> $brochure
 * @var string               $backUrl
 */
$errors = session('errors') ?? [];
?>

<div class="rs-shell pt-8">
    <?php /* `url` is NOT optional here — the partial reads $crumb['url']
             directly, so omitting it is an ErrorException rather than a
             missing link. Null means "this is where you are". */ ?>
    <?= view('partials/breadcrumbs', ['crumbs' => [
        ['label' => $brochure['title'], 'url' => null],
    ]]) ?>
</div>

<section class="rs-section">
    <div class="rs-shell">
        <div class="mx-auto max-w-xl">
            <p class="rs-kicker">Brochure</p>
            <h1 class="rs-display mt-2 text-3xl"><?= esc($brochure['title']) ?></h1>

            <?php if (! empty($brochure['description'])): ?>
                <p class="mt-3 text-sm text-ink-soft"><?= esc($brochure['description']) ?></p>
            <?php endif; ?>

            <p class="mt-4 text-sm text-ink-soft">
                Tell us where to send it and the download starts straight away.
            </p>

            <form method="post" action="<?= site_url('brochure/' . (int) $brochure['id']) ?>"
                  class="mt-6 grid gap-4">
                <?= csrf_field() ?>

                <?php /* Honeypot. A bot fills every field it finds; a person never
                         sees this one. Hidden from assistive tech too, or a screen
                         reader would announce a field nobody should complete. */ ?>
                <div class="rs-hp" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <?php /* Where they were, so the lead says which page prompted it. */ ?>
                <input type="hidden" name="source_type" value="<?= esc((string) ($sourceType ?? ''), 'attr') ?>">
                <input type="hidden" name="source_id" value="<?= (int) ($sourceId ?? 0) ?>">
                <input type="hidden" name="source_url" value="<?= esc($backUrl, 'attr') ?>">

                <label class="block">
                    <span class="rs-label">Your name <abbr class="rs-req" title="required">*</abbr></span>
                    <input type="text" name="name" class="rs-input" required maxlength="191"
                           autocomplete="name" value="<?= esc(old('name'), 'attr') ?>">
                    <?php if (isset($errors['name'])): ?>
                        <span class="rs-fielderr"><?= esc($errors['name']) ?></span>
                    <?php endif; ?>
                </label>

                <?php /* Optional. Name and phone are the mandatory pair; an address
                         that is given is still validated, because one that cannot
                         receive mail looks like a usable lead and is not. */ ?>
                <label class="block">
                    <span class="rs-label">Email <span class="text-ink-muted">(optional)</span></span>
                    <input type="email" name="email" class="rs-input" maxlength="191"
                           autocomplete="email" value="<?= esc(old('email'), 'attr') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="rs-fielderr"><?= esc($errors['email']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="block">
                    <span class="rs-label">Phone <abbr class="rs-req" title="required">*</abbr></span>
                    <input type="tel" name="phone" class="rs-input" required maxlength="40"
                           autocomplete="tel" value="<?= esc(old('phone'), 'attr') ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="rs-fielderr"><?= esc($errors['phone']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="block">
                    <span class="rs-label">Anything we should know? <span class="text-ink-muted">(optional)</span></span>
                    <textarea name="notes" rows="3" class="rs-input" maxlength="2000"
                              placeholder="Quantities, timelines, a budget to work to."><?= esc(old('notes')) ?></textarea>
                </label>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="rs-btn rs-btn--primary">Download the brochure</button>
                    <a href="<?= $backUrl ?>" class="rs-link text-sm text-ink-muted">Back</a>
                </div>
            </form>

            <?php if (session('customer_id') === null): ?>
                <p class="mt-6 text-xs text-ink-muted">
                    Already have an account?
                    <a class="rs-link" href="<?= site_url('login') ?>">Sign in</a>
                    and the download starts without the form.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
