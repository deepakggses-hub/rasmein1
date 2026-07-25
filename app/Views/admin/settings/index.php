<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array<string, array<int, array<string, mixed>>> $groups
 * @var bool $canManage, $canSwitchMode
 */
$isEnquire = $journeyMode === \Config\Rasmein::MODE_ENQUIRE;
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'System',
    'heading'    => 'Settings',
    'subheading' => 'Changes take effect on the next page load. Every change is logged.',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">

    <!-- ============ the master switch: its own card, its own permission ==== -->
    <section class="border-2 border-mulberry bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="rs-eyebrow rs-eyebrow--plain">How the store sells</h2>
                <p class="mt-2 font-display text-xl font-semibold">
                    Currently: <?= $isEnquire ? 'Enquire now' : 'Buy now' ?>
                </p>
                <p class="mt-2 max-w-xl text-sm text-ink-muted">
                    <?php if ($isEnquire): ?>
                        Every basket is captured as a lead. Carts read as enquiry lists,
                        checkout is an enquiry form, and nothing is charged online.
                    <?php else: ?>
                        Customers order directly. Products pinned to <em>Enquire</em>
                        still quote individually, and any basket containing one becomes
                        an enquiry.
                    <?php endif; ?>
                </p>
            </div>
            <span class="rs-badge <?= $isEnquire ? 'rs-badge--enquire' : 'rs-badge--brass' ?> shrink-0">
                <?= $isEnquire ? 'Enquire' : 'Buy' ?>
            </span>
        </div>

        <?php if (! $canSwitchMode): ?>
            <p class="rs-help mt-4">
                Switching this needs the <span class="font-mono">settings.journey_mode</span>
                permission, which your role does not have.
            </p>
        <?php else: ?>
            <form method="post" action="<?= site_url('admin/settings/journey') ?>"
                  class="mt-5 flex flex-wrap items-end gap-3 border-t border-shell-line pt-5">
                <?= csrf_field() ?>
                <label>
                    <span class="rs-label">Switch to</span>
                    <select name="journey_mode" class="rs-select w-auto">
                        <?php foreach ($journeyModes as $key => $label): ?>
                            <option value="<?= esc($key, 'attr') ?>" <?= $journeyMode === $key ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="rs-label">Type SWITCH to confirm</span>
                    <input type="text" name="confirm" class="rs-input w-40" autocomplete="off" placeholder="SWITCH">
                </label>
                <button type="submit" class="rs-btn rs-btn--primary">Change how the store sells</button>
            </form>
            <p class="rs-help mt-2">
                This changes every product page, cart and checkout immediately. The
                confirmation is there because a mis-click is expensive.
            </p>
        <?php endif; ?>
    </section>

    <!-- ============================ the rest ============================ -->
    <form method="post" action="<?= site_url('admin/settings') ?>">
        <?= csrf_field() ?>

        <?php foreach ($groups as $groupName => $settings): ?>
            <section class="mt-5 border border-shell-line bg-white">
                <h2 class="border-b border-shell-line px-4 py-3 font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                    <?= esc(ucfirst(str_replace('_', ' ', $groupName))) ?>
                </h2>

                <div class="divide-y divide-shell-line">
                    <?php foreach ($settings as $setting): ?>
                        <?php
                        $locked = (int) $setting['is_locked'] === 1;
                        $name   = 'settings[' . $setting['key_name'] . ']';
                        $label  = $setting['label'] ?: ucfirst(str_replace('_', ' ', $setting['key_name']));
                        ?>
                        <div class="flex flex-wrap items-start gap-4 px-4 py-3">
                            <div class="min-w-52 flex-1">
                                <p class="text-sm font-medium"><?= esc($label) ?></p>
                                <p class="font-mono text-[0.5625rem] tracking-[0.1em] text-ink-muted"><?= esc($setting['key_name']) ?></p>
                                <?php if (! empty($setting['description'])): ?>
                                    <p class="rs-help max-w-xl"><?= esc($setting['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="w-56">
                                <?php if ($locked): ?>
                                    <p class="text-sm">
                                        <span class="num font-medium"><?= esc($setting['value'] ?: '—') ?></span>
                                        <span class="rs-badge rs-badge--soft ml-2">Locked</span>
                                    </p>
                                    <p class="rs-help">Changed through its own guarded control.</p>
                                <?php elseif ($setting['value_type'] === 'bool'): ?>
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="checkbox" name="<?= esc($name, 'attr') ?>" value="1"
                                               class="accent-mulberry"
                                               <?= (string) $setting['value'] === '1' ? 'checked' : '' ?>
                                               <?= $canManage ? '' : 'disabled' ?>>
                                        <span>Enabled</span>
                                    </label>
                                <?php elseif ($setting['value_type'] === 'json'): ?>
                                    <textarea name="<?= esc($name, 'attr') ?>" class="rs-textarea font-mono text-xs"
                                              rows="2" <?= $canManage ? '' : 'disabled' ?>><?= esc($setting['value'] ?? '') ?></textarea>
                                <?php else: ?>
                                    <input type="<?= $setting['value_type'] === 'int' || $setting['value_type'] === 'decimal' ? 'number' : 'text' ?>"
                                           <?= $setting['value_type'] === 'decimal' ? 'step="0.01"' : '' ?>
                                           name="<?= esc($name, 'attr') ?>" class="rs-input num"
                                           value="<?= esc($setting['value'] ?? '', 'attr') ?>"
                                           <?= $canManage ? '' : 'disabled' ?>>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($canManage): ?>
            <div class="mt-5">
                <button type="submit" class="rs-btn rs-btn--primary">Save settings</button>
            </div>
        <?php else: ?>
            <p class="rs-help mt-5">Your role can view settings but not change them.</p>
        <?php endif; ?>
    </form>
</div>

<?= $this->endSection() ?>
