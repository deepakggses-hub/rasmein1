<?= $this->extend('layouts/storefront') ?>
<?= $this->section('content') ?>
<?php $v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr'); ?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6">Your account</p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">Addresses</h1>
        <p class="mt-3 text-ink-muted">Saved here so checkout is one step shorter next time.</p>
    </div>
</header>

<div class="rs-shell grid gap-8 py-10 lg:grid-cols-[14rem_1fr] lg:py-14">
    <?= view('partials/account_nav') ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
        <div class="space-y-4">
            <?php if ($addresses === []): ?>
                <p class="border border-shell-line bg-white px-4 py-8 text-sm text-ink-muted">
                    No addresses saved yet. Add one alongside.
                </p>
            <?php endif; ?>

            <?php foreach ($addresses as $address): ?>
                <article class="border <?= $address['is_default_shipping'] ? 'border-brass' : 'border-shell-line' ?> bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                <?= esc($address['recipient_name']) ?>
                                <?php if (! empty($address['label'])): ?>
                                    <span class="rs-badge rs-badge--soft ml-1"><?= esc($address['label']) ?></span>
                                <?php endif; ?>
                                <?php if ($address['is_default_shipping']): ?>
                                    <span class="rs-badge rs-badge--brass ml-1">Default</span>
                                <?php endif; ?>
                            </p>
                            <address class="mt-2 text-sm leading-relaxed not-italic text-ink-muted">
                                <?= esc($address['line1']) ?><br>
                                <?php if (! empty($address['line2'])): ?><?= esc($address['line2']) ?><br><?php endif; ?>
                                <?php if (! empty($address['landmark'])): ?><?= esc($address['landmark']) ?><br><?php endif; ?>
                                <?= esc($address['city']) ?>, <?= esc($address['state']) ?>
                                <span class="num"><?= esc($address['postal_code']) ?></span><br>
                                <span class="num"><?= esc($address['phone']) ?></span>
                            </address>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 text-sm">
                            <a href="<?= site_url('account/addresses') ?>?edit=<?= (int) $address['id'] ?>" class="rs-link text-ink-muted">Edit</a>
                            <?php if (! $address['is_default_shipping']): ?>
                                <form method="post" action="<?= site_url('account/addresses/default') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                                    <button type="submit" class="rs-link text-ink-muted">Make default</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= site_url('account/addresses/delete') ?>"
                                  onsubmit="return confirm('Remove this address?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                                <button type="submit" class="rs-link text-ink-muted hover:text-bad">Remove</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= site_url('account/addresses') ?>" class="border border-shell-line bg-white p-5">
            <?= csrf_field() ?>
            <?php if ($editing !== null): ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <h2 class="rs-eyebrow rs-eyebrow--plain"><?= $editing === null ? 'Add an address' : 'Edit address' ?></h2>

            <label class="mt-4 block">
                <span class="rs-label">Label <span class="text-ink-muted">(optional)</span></span>
                <input type="text" name="label" class="rs-input" maxlength="40" placeholder="Home"
                       value="<?= $v('label', $editing['label'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Recipient name</span>
                <input type="text" name="recipient_name" class="rs-input" required maxlength="120"
                       value="<?= $v('recipient_name', $editing['recipient_name'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Phone</span>
                <input type="tel" name="phone" class="rs-input" required maxlength="20"
                       value="<?= $v('phone', $editing['phone'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Address</span>
                <input type="text" name="line1" class="rs-input" required maxlength="191"
                       value="<?= $v('line1', $editing['line1'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Area <span class="text-ink-muted">(optional)</span></span>
                <input type="text" name="line2" class="rs-input" maxlength="191"
                       value="<?= $v('line2', $editing['line2'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Landmark <span class="text-ink-muted">(optional)</span></span>
                <input type="text" name="landmark" class="rs-input" maxlength="120"
                       value="<?= $v('landmark', $editing['landmark'] ?? '') ?>">
            </label>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <label>
                    <span class="rs-label">PIN code</span>
                    <input type="text" name="postal_code" class="rs-input num" required
                           inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6"
                           value="<?= $v('postal_code', $editing['postal_code'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">City</span>
                    <input type="text" name="city" class="rs-input" required maxlength="80"
                           value="<?= $v('city', $editing['city'] ?? '') ?>">
                </label>
            </div>
            <label class="mt-4 block">
                <span class="rs-label">State</span>
                <input type="text" name="state" class="rs-input" required maxlength="80"
                       value="<?= $v('state', $editing['state'] ?? '') ?>">
            </label>
            <label class="mt-4 flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="is_default_shipping" value="1" class="accent-mulberry"
                       <?= ($editing !== null && $editing['is_default_shipping']) ? 'checked' : '' ?>>
                <span>Use this by default</span>
            </label>

            <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                <?= $editing === null ? 'Save address' : 'Update address' ?>
            </button>
            <?php if ($editing !== null): ?>
                <a href="<?= site_url('account/addresses') ?>" class="rs-btn rs-btn--outline rs-btn--sm mt-2 w-full">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
