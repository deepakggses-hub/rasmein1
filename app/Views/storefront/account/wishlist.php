<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Your account</p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">Saved for later</h1>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div>
        <?php if ($items === []): ?>
            <div class="border border-shell-line bg-white px-4 py-14 text-center">
                <div class="mx-auto max-w-40">
                    <?= view('partials/tray', ['capacity' => 4, 'filled' => [], 'columns' => 2]) ?>
                </div>
                <p class="mt-8 text-ink-muted">Nothing saved yet.</p>
                <a href="<?= site_url('shop') ?>" class="rs-btn rs-btn--primary mt-5">Browse the shop</a>
            </div>
        <?php else: ?>
            <ul class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($items as $item): ?>
                    <li class="rs-card flex flex-col overflow-hidden bg-white">
                        <a href="<?= site_url('product/' . $item['slug']) ?>" class="block aspect-[4/5] overflow-hidden bg-shell-deep">
                            <img src="<?= esc(rs_image($item['image'], 'products'), 'attr') ?>"
                                 alt="<?= esc($item['name'], 'attr') ?>" loading="lazy"
                                 class="h-full w-full object-cover">
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            <h2 class="text-base leading-snug font-semibold">
                                <a href="<?= site_url('product/' . $item['slug']) ?>" class="rs-link"><?= esc($item['name']) ?></a>
                            </h2>
                            <p class="num mt-2 text-lg font-bold text-mulberry"><?= rs_money($item['price']) ?></p>

                            <?php
                            $inStock = (int) $item['track_inventory'] !== 1 || (int) $item['stock_qty'] > 0;
                            $live    = (int) $item['is_active'] === 1;
                            ?>
                            <?php if (! $live): ?>
                                <p class="mt-2 text-sm text-bad">No longer available.</p>
                            <?php elseif (! $inStock): ?>
                                <p class="mt-2 text-sm text-bad">Sold out — we will restock.</p>
                            <?php endif; ?>

                            <div class="mt-auto flex gap-2 pt-4">
                                <?php if ($live && $inStock): ?>
                                    <form method="post" action="<?= site_url('cart/add') ?>" class="flex-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="return_to" value="wishlist">
                                        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm w-full">
                                            <?= esc(rs_cta_label($item['sale_mode'], 'add')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= site_url('wishlist/toggle') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                    <input type="hidden" name="return_to" value="wishlist">
                                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm"
                                            aria-label="Remove <?= esc($item['name'], 'attr') ?>">Remove</button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
