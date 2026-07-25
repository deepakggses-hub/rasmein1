<?php /** @var string $heading */ ?>
<header class="border-b border-shell-line bg-white px-5 py-5 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <?php if (! empty($eyebrow)): ?>
                <p class="rs-eyebrow rs-eyebrow--plain"><?= esc($eyebrow) ?></p>
            <?php endif; ?>
            <h1 class="mt-1.5 font-display text-2xl font-semibold"><?= esc($heading) ?></h1>
            <?php if (! empty($subheading)): ?>
                <p class="mt-1 text-sm text-ink-muted"><?= esc($subheading) ?></p>
            <?php endif; ?>
        </div>
        <?php if (! empty($actions)): ?>
            <div class="flex flex-wrap gap-2"><?= $actions ?></div>
        <?php endif; ?>
    </div>
</header>
