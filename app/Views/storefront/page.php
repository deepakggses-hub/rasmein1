<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php /** @var array<string, mixed> $page */ ?>

<article class="rs-shell py-14 lg:py-20">
    <nav class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2">
            <li><a href="<?= site_url('/') ?>" class="rs-link">Home</a></li>
            <li aria-hidden="true" class="rs-gold">/</li>
            <li aria-current="page"><?= esc($page['title']) ?></li>
        </ol>
    </nav>

    <header class="mt-6 max-w-2xl">
        <h1 class="text-4xl sm:text-[2.75rem]"><?= esc($page['title']) ?></h1>
        <?php if (! empty($page['excerpt'])): ?>
            <p class="mt-4 text-lg leading-relaxed text-ink-muted"><?= esc($page['excerpt']) ?></p>
        <?php endif; ?>
        <hr class="rs-rule mt-8">
    </header>

    <div class="rs-prose mt-8">
        <?php
        /*
         * Page bodies are authored by staff in the admin panel. They are stored
         * as a limited HTML subset and passed through CI4's sanitizer on save,
         * so they render as markup here. Anything a *customer* submits is
         * always escaped instead — never rendered as HTML.
         */
        echo $page['content'] ?? '';
        ?>
    </div>

    <?php /* Every key the partial reads is named EXPLICITLY, even when empty:
             view() merges the parent's data, so `$sourceId ?? null` inside a
             partial is not a default — it is whatever the parent happens to
             hold. That is what once drew one photograph on twelve cards. */ ?>
    <div class="mt-12">
        <?= view('partials/brochure_cta', [
            'brochure'   => $brochure ?? null,
            'sourceType' => $brochureSource ?? null,
            'sourceId'   => $brochureSourceId ?? null,
        ]) ?>
    </div>

    <footer class="mt-14 border-t border-shell-line pt-6">
        <p class="text-sm text-ink-muted">
            Something not covered here?
            <a href="mailto:<?= esc($brand->supportEmail, 'attr') ?>" class="rs-link text-mulberry font-medium">
                Write to us
            </a>
            and a person will reply.
        </p>
    </footer>
</article>

<?= $this->endSection() ?>
