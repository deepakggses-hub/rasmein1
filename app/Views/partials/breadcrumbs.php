<?php
/**
 * @var array<int, array{label: string, url: string|null}> $crumbs
 */
$crumbs = $crumbs ?? [];
if ($crumbs === []) { return; }
?>
<nav class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-2">
        <li><a href="<?= site_url('/') ?>" class="rs-link">Home</a></li>
        <?php foreach ($crumbs as $crumb): ?>
            <li aria-hidden="true" class="text-brass">/</li>
            <li<?= $crumb['url'] === null ? ' aria-current="page"' : '' ?>>
                <?php if ($crumb['url'] !== null): ?>
                    <a href="<?= $crumb['url'] ?>" class="rs-link"><?= esc($crumb['label']) ?></a>
                <?php else: ?>
                    <?= esc(rs_excerpt($crumb['label'], 40)) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
