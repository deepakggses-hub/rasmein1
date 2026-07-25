<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php /** @var array<int, array<string, mixed>> $collections */ ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10 lg:py-14">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Curated</p>
        <h1 class="mt-4 max-w-2xl text-4xl sm:text-[2.75rem]">Collections</h1>
        <p class="mt-4 max-w-xl leading-relaxed text-ink-muted">
            Edits built around a single moment. Send one as it is, or use it as a
            starting point and swap things out in the builder.
        </p>
    </div>
</header>

<div class="rs-shell py-12 lg:py-16">
    <?php if ($collections === []): ?>
        <p class="text-ink-muted">No collections are live right now.</p>
    <?php else: ?>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($collections as $collection): ?>
                <a href="<?= site_url('collections/' . $collection['slug']) ?>"
                   class="group relative block aspect-[4/3] overflow-hidden bg-ink">
                    <img src="<?= esc(rs_image($collection['image'] ?? null, 'products'), 'attr') ?>"
                         alt="<?= esc($collection['name'], 'attr') ?>"
                         loading="lazy" decoding="async"
                         class="h-full w-full object-cover opacity-80 transition duration-500 group-hover:scale-105 group-hover:opacity-70">
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/95 to-transparent p-5">
                        <span class="block font-display text-xl font-semibold text-shell"><?= esc($collection['name']) ?></span>
                        <?php if (! empty($collection['description'])): ?>
                            <span class="mt-1 block text-sm text-shell/75">
                                <?= esc(rs_excerpt($collection['description'], 72)) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
