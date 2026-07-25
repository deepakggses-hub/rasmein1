<?php
/** @var \CodeIgniter\Pager\Pager $pager */
if (! isset($pager) || $pager->getPageCount() <= 1) { return; }
$previous = $pager->getPreviousPageURI();
$next     = $pager->getNextPageURI();
?>
<nav class="flex items-center justify-between gap-4 border-t border-shell-line px-4 py-3 text-sm" aria-label="Pagination">
    <?php if ($previous !== null): ?>
        <a href="<?= esc($previous, 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm" rel="prev">Previous</a>
    <?php else: ?>
        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Previous</span>
    <?php endif; ?>
    <p class="num font-mono text-[0.625rem] tracking-widest text-ink-muted uppercase">
        Page <?= $pager->getCurrentPage() ?> of <?= $pager->getPageCount() ?>
    </p>
    <?php if ($next !== null): ?>
        <a href="<?= esc($next, 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm" rel="next">Next</a>
    <?php else: ?>
        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Next</span>
    <?php endif; ?>
</nav>
