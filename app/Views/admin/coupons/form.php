<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $coupon === null;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$dt = static fn (?string $value): string => $value !== null ? date('Y-m-d\TH:i', strtotime($value)) : '';
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Sales',
    'heading'    => $isNew ? 'New coupon' : $coupon['code'],
    'subheading' => $isNew ? null : $redemptions . ' redemption' . ($redemptions === 1 ? '' : 's') . ' recorded.',
    'actions'    => '<a href="' . site_url('admin/coupons') . '" class="rs-btn rs-btn--outline rs-btn--sm">All coupons</a>',
]) ?>

<form method="post" action="<?= $isNew ? site_url('admin/coupons') : site_url('admin/coupons/' . $coupon['id']) ?>"
      class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">The offer</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Code <span class="text-bad">*</span></span>
                    <input type="text" name="code" class="rs-input num font-mono uppercase" required maxlength="40"
                           autocapitalize="characters" value="<?= $v('code', $coupon['code'] ?? '') ?>">
                    <span class="rs-help">Capitals, numbers, hyphens and underscores.</span>
                </label>
                <label>
                    <span class="rs-label">Type <span class="text-bad">*</span></span>
                    <select name="discount_type" class="rs-select">
                        <?php foreach ([
                            'percent'       => 'Percentage off',
                            'fixed'         => 'Fixed amount off',
                            'free_shipping' => 'Free delivery',
                        ] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= (old('discount_type') ?? $coupon['discount_type'] ?? 'percent') === $k ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="rs-label">Value</span>
                    <input type="number" name="value" class="rs-input num" step="0.01" min="0"
                           value="<?= $v('value', (string) ($coupon['value'] ?? '')) ?>">
                    <span class="rs-help">A percentage, or an amount. Ignored for free delivery.</span>
                </label>
                <label>
                    <span class="rs-label">Cap the discount at</span>
                    <input type="number" name="max_discount" class="rs-input num" step="0.01" min="0"
                           value="<?= $v('max_discount', (string) ($coupon['max_discount'] ?? '')) ?>">
                    <span class="rs-help">Stops a percentage running away on a big basket.</span>
                </label>
                <label>
                    <span class="rs-label">Minimum order</span>
                    <input type="number" name="min_order_value" class="rs-input num" step="0.01" min="0"
                           value="<?= $v('min_order_value', (string) ($coupon['min_order_value'] ?? 0)) ?>">
                </label>
                <label>
                    <span class="rs-label">Description</span>
                    <input type="text" name="description" class="rs-input" maxlength="255"
                           value="<?= $v('description', $coupon['description'] ?? '') ?>">
                    <span class="rs-help">Shown in the cart when applied.</span>
                </label>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Limits</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Total redemptions</span>
                    <input type="number" name="usage_limit_total" class="rs-input num" min="1"
                           value="<?= $v('usage_limit_total', (string) ($coupon['usage_limit_total'] ?? '')) ?>">
                    <span class="rs-help">Blank means unlimited.</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Per customer</span>
                    <input type="number" name="usage_limit_per_customer" class="rs-input num" min="1"
                           value="<?= $v('usage_limit_per_customer', (string) ($coupon['usage_limit_per_customer'] ?? '')) ?>">
                </label>
                <?php if (! $isNew): ?>
                    <p class="num rs-help mt-3">Used <?= (int) $coupon['used_count'] ?> time(s) so far.</p>
                <?php endif; ?>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Window</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Starts</span>
                    <input type="datetime-local" name="starts_at" class="rs-input"
                           value="<?= esc(old('starts_at') ?? $dt($coupon['starts_at'] ?? null), 'attr') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Ends</span>
                    <input type="datetime-local" name="ends_at" class="rs-input"
                           value="<?= esc(old('ends_at') ?? $dt($coupon['ends_at'] ?? null), 'attr') ?>">
                </label>
                <p class="rs-help mt-2">Leave either blank for an open-ended window.</p>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">State</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $coupon['is_active']) ? 'checked' : '' ?>>
                    <span>Accepting this code</span>
                </label>
                <label class="mt-3 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="first_order_only" value="1" class="accent-mulberry"
                           <?= (! $isNew && $coupon['first_order_only']) ? 'checked' : '' ?>>
                    <span>First order only</span>
                </label>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create coupon' : 'Save coupon' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/coupons/' . $coupon['id'] . '/delete') ?>"
              data-confirm="Remove this coupon? Orders that used it keep their record." data-confirm-action="Yes, do it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this coupon</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
