<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php /** @var array<int, \App\Entities\GiftBox> $boxes */ ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10 lg:py-14">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Step one of four</p>
        <h1 class="mt-4 max-w-2xl text-4xl sm:text-[2.75rem]">Choose a box.</h1>
        <p class="mt-4 max-w-xl leading-relaxed text-ink-muted">
            The box decides how many compartments you have to fill. You can change your
            mind later — nothing is committed until you say so.
        </p>
    </div>
</header>

<div class="rs-shell py-12 lg:py-16">
    <?php if ($boxes === []): ?>
        <p class="text-ink-muted">No boxes are available right now.</p>
    <?php else: ?>
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($boxes as $box): ?>
                <article class="rs-card flex flex-col overflow-hidden bg-white">
                    <div class="aspect-[3/2] overflow-hidden bg-shell-deep">
                        <img src="<?= esc($box->imageUrl(), 'attr') ?>"
                             alt="<?= esc($box->name, 'attr') ?>"
                             loading="lazy" decoding="async"
                             class="h-full w-full object-cover">
                    </div>

                    <div class="flex flex-1 flex-col p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($box->size_label !== null && $box->size_label !== ''): ?>
                                <span class="rs-badge rs-badge--soft"><?= esc($box->size_label) ?></span>
                            <?php endif; ?>
                            <span class="rs-badge rs-badge--brass"><?= esc($box->capacityLabel()) ?></span>
                            <?php if ($box->isEnquireOnly()): ?>
                                <span class="rs-badge rs-badge--enquire">Quoted</span>
                            <?php endif; ?>
                        </div>

                        <h2 class="mt-3 font-display text-xl font-semibold"><?= esc($box->name) ?></h2>

                        <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                            <?= esc(rs_excerpt($box->description, 110)) ?>
                        </p>

                        <!-- A miniature of the tray, so the idea is obvious before
                             you commit to a design. -->
                        <div class="mt-5 max-w-32">
                            <?= view('partials/tray', [
                                'capacity' => min(9, (int) $box->capacity_slots),
                                'filled'   => [],
                                'columns'  => (int) $box->capacity_slots <= 4 ? 2 : 3,
                            ]) ?>
                        </div>

                        <dl class="mt-5 space-y-1 text-sm">
                            <div class="num flex justify-between gap-3">
                                <dt class="text-ink-muted">Box</dt>
                                <dd class="font-medium"><?= esc($box->formattedBasePrice()) ?></dd>
                            </div>
                            <div class="num flex justify-between gap-3">
                                <dt class="text-ink-muted">Fill at least</dt>
                                <dd class="font-medium"><?= (int) $box->min_slots ?> of <?= (int) $box->capacity_slots ?></dd>
                            </div>
                        </dl>

                        <a href="<?= site_url('build/' . $box->slug) ?>" class="rs-btn rs-btn--primary mt-6 w-full">
                            Fill this box
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
