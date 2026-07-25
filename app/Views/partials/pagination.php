<?php
/**
 * Pagination, built by hand rather than through CI4's pager templates so the
 * markup matches the rest of the design system.
 *
 * @var \CodeIgniter\Pager\Pager $pager
 */
if (! isset($pager) || $pager->getPageCount() <= 1) { return; }

$current  = $pager->getCurrentPage();
$last     = $pager->getPageCount();
$previousUri = $pager->getPreviousPageURI();
$nextUri     = $pager->getNextPageURI();

// A sliding window: always show first and last, plus two either side of here.
$window = range(max(1, $current - 2), min($last, $current + 2));
$pages  = array_values(array_unique(array_merge([1], $window, [$last])));
sort($pages);
?>
<nav class="mt-14 flex items-center justify-between gap-4 border-t border-shell-line pt-6" aria-label="Pagination">
    <?php if ($previousUri !== null): ?>
        <a href="<?= esc($previousUri, 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm" rel="prev">
            <span aria-hidden="true">&larr;</span> Previous
        </a>
    <?php else: ?>
        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true"><span aria-hidden="true">&larr;</span> Previous</span>
    <?php endif; ?>

    <ol class="hidden items-center gap-1 sm:flex">
        <?php $previous = 0; ?>
        <?php foreach ($pages as $page): ?>
            <?php if ($previous !== 0 && $page > $previous + 1): ?>
                <li class="px-2 font-mono text-xs text-ink-muted" aria-hidden="true">…</li>
            <?php endif; ?>
            <li>
                <?php if ($page === $current): ?>
                    <span class="num flex h-9 min-w-9 items-center justify-center bg-mulberry px-2 font-mono text-xs text-shell"
                          aria-current="page"><?= $page ?></span>
                <?php else: ?>
                    <a href="<?= esc($pager->getPageURI($page), 'attr') ?>"
                       class="num flex h-9 min-w-9 items-center justify-center border border-shell-line px-2 font-mono text-xs hover:border-brass hover:text-mulberry"
                       aria-label="Page <?= $page ?>"><?= $page ?></a>
                <?php endif; ?>
            </li>
            <?php $previous = $page; ?>
        <?php endforeach; ?>
    </ol>

    <p class="num font-mono text-[0.6875rem] tracking-widest text-ink-muted uppercase sm:hidden">
        <?= $current ?> / <?= $last ?>
    </p>

    <?php if ($nextUri !== null): ?>
        <a href="<?= esc($nextUri, 'attr') ?>" class="rs-btn rs-btn--outline rs-btn--sm" rel="next">
            Next <span aria-hidden="true">&rarr;</span>
        </a>
    <?php else: ?>
        <span class="rs-btn rs-btn--outline rs-btn--sm" aria-disabled="true">Next <span aria-hidden="true">&rarr;</span></span>
    <?php endif; ?>
</nav>
