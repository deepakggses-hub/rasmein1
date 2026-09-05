<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php
/**
 * The wishlist, for anyone.
 *
 * No account sidebar: this page is reachable without signing in, so it must not
 * look like part of an account area the visitor does not have. A signed-in
 * customer still gets there from the account nav.
 *
 * @var array $products
 * @var bool  $isGuest
 */
?>

<div class="rs-shell pt-6">
    <?= view('partials/breadcrumbs', ['crumbs' => [['label' => 'Saved for later', 'url' => null]]]) ?>
</div>

<div class="rs-shell py-8 lg:py-12">
    <header class="border-b border-shell-line pb-5">
        <h1 class="rs-display rs-display--lg">Saved for later</h1>
        <p class="mt-3 font-mono text-[0.625rem] tracking-[0.18em] text-ink-muted uppercase">
            <span class="num"><?= count($products) ?></span>
            <?= count($products) === 1 ? 'piece' : 'pieces' ?>
        </p>
    </header>

    <?php if ($isGuest && $products !== []): ?>
        <?php /* Not a wall — the list works perfectly well without an account.
                 This is only so the saves are not lost with the cookie. */ ?>
        <p class="mt-6 border border-shell-line bg-white px-4 py-3 text-sm text-ink-soft">
            These are saved in this browser.
            <a href="<?= site_url('account/login') ?>" class="rs-link text-mulberry">Sign in</a>
            and we will keep them on your account.
        </p>
    <?php endif; ?>

    <?php if ($products === []): ?>
        <div class="mt-8 border border-shell-line bg-white px-4 py-16 text-center">
            <p class="font-display text-xl">Nothing saved yet.</p>
            <p class="mx-auto mt-3 max-w-sm text-sm text-ink-muted">
                Tap the heart on anything you like and it will wait for you here —
                no account needed.
            </p>
            <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--primary mt-7">Browse the shop</a>
        </div>
    <?php else: ?>
        <div class="rs-grid mt-8">
            <?php
            $imageMap = model(\App\Models\ProductModel::class)->imagesFor(
                array_map(static fn ($p): int => (int) $p->id, $products)
            );
            ?>
            <?php foreach ($products as $product): ?>
                <?php /* Everything here IS saved, by definition — so every
                         heart is filled. Tapping one removes it, and the card
                         goes with it rather than sitting there empty. */ ?>
                <div data-wish-card>
                    <?= view('partials/product_card', [
                        'product' => $product,
                        'images'  => $imageMap[(int) $product->id] ?? [],
                        'saved'   => true,
                    ]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
