<?php
$links = [
    'account'           => 'Overview',
    'account/orders'    => 'Orders',
    'account/addresses' => 'Addresses',
    'wishlist'          => 'Wishlist',
];
?>
<nav class="border border-shell-line bg-white" aria-label="Your account">
    <ul class="divide-y divide-shell-line text-sm">
        <?php foreach ($links as $path => $label): ?>
            <li>
                <a href="<?= site_url($path) ?>"
                   class="block px-4 py-2.5 <?= rs_active($path, 'bg-shell-deep font-semibold text-mulberry') ?: 'hover:bg-shell' ?>">
                    <?= esc($label) ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li>
            <form method="post" action="<?= site_url('account/logout') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="w-full px-4 py-2.5 text-left text-ink-muted hover:bg-shell hover:text-bad">
                    Sign out
                </button>
            </form>
        </li>
    </ul>
</nav>
