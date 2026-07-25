<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * Shown for navigation destinations whose feature is still being built.
 * Honest about what is missing and what to do instead.
 *
 * @var string $phase
 * @var string $pageTitle
 * @var string $milestone
 */
?>

<section class="rs-shell flex min-h-[60vh] items-center py-20">
    <div class="grid w-full items-center gap-14 lg:grid-cols-[1fr_auto]">
        <div class="max-w-xl">
            <p class="rs-eyebrow">In build · Phase <?= esc($phase) ?></p>

            <h1 class="mt-5 text-4xl sm:text-5xl">
                <?= esc(ucfirst($pageTitle)) ?> is being built.
            </h1>

            <p class="mt-5 text-lg leading-relaxed text-ink-muted">
                This arrives with <span class="text-ink font-medium"><?= esc($milestone) ?></span>.
                Everything else on the site works — have a look around in the meantime.
            </p>

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="<?= site_url('/') ?>" class="rs-btn rs-btn--primary">Back to home</a>
                <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>" class="rs-btn rs-btn--outline">
                    Ask us directly
                </a>
            </div>
        </div>

        <!-- The tray, empty. The signature element does the waiting for us. -->
        <div class="mx-auto w-full max-w-xs">
            <?= view('partials/tray', ['capacity' => 6, 'filled' => [], 'columns' => 3]) ?>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
