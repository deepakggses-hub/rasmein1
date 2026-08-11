<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php $v = static fn (string $k) => esc((string) (old($k) ?? $values[$k] ?? ''), 'attr'); ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'System',
    'heading'    => 'Appearance',
    'subheading' => 'Page width, how many products sit in a row, corners and colour.',
    'actions'    => '<a href="' . site_url('/') . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View the storefront</a>',
]) ?>

<?php if ($missing > 0): ?>
    <div class="px-5 pt-6 lg:px-8">
        <section class="border-2 border-brass bg-brass-soft/25 p-5">
            <h2 class="font-display text-lg font-semibold"><?= (int) $missing ?> setting(s) are not installed.</h2>
            <p class="rs-help mt-2">Run <code class="font-mono">php spark db:seed DesignSettingSeeder</code>, or press below.</p>
            <form method="post" action="<?= site_url('admin/appearance/reset') ?>" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="rs-btn rs-btn--primary">Install the defaults</button>
            </form>
        </section>
    </div>
<?php endif; ?>

<form method="post" action="<?= site_url('admin/appearance') ?>" class="space-y-6 px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- ============================ layout ============================ -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Page width</h2>
            <div class="mt-4 space-y-2">
                <?php foreach ([
                    'full'  => ['Full bleed', 'Uses the whole screen. Best for a large catalogue and big photography.'],
                    'wide'  => ['Wide', 'Full width until the maximum below, then centred. Stops lines getting too long on an ultrawide.'],
                    'boxed' => ['Boxed', 'A classic centred column.'],
                ] as $key => [$label, $note]): ?>
                    <label class="flex items-start gap-3 border <?= ($values['design_container'] ?? 'full') === $key ? 'border-mulberry bg-shell' : 'border-shell-line' ?> px-4 py-3 text-sm">
                        <input type="radio" name="design_container" value="<?= $key ?>" class="mt-0.5 accent-mulberry"
                               <?= ($values['design_container'] ?? 'full') === $key ? 'checked' : '' ?>
                               <?= $canManage ? '' : 'disabled' ?>>
                        <span>
                            <span class="font-medium"><?= esc($label) ?></span>
                            <span class="rs-help"><?= esc($note) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Maximum width (px)</span>
                    <input type="number" name="design_max_width" class="rs-input num" min="960" max="3840" step="20"
                           value="<?= $v('design_max_width') ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Ignored on full bleed.</span>
                </label>
                <label>
                    <span class="rs-label">Side spacing (rem)</span>
                    <input type="number" name="design_gutter" class="rs-input num" min="1" max="8" step="0.25"
                           value="<?= $v('design_gutter') ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Shrinks automatically on smaller screens.</span>
                </label>
            </div>
        </section>

        <!-- ============================= grid ============================= -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Product grid</h2>
            <p class="rs-help mt-2 max-w-prose">
                Columns are not fixed. You set the narrowest a card may be and the browser
                fits as many as the screen allows — so the same setting gives two columns on
                a phone and six on a wide monitor, with nothing to configure per device.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Narrowest card (rem)</span>
                    <input type="number" name="design_card_min" class="rs-input num" min="10" max="30" step="0.5"
                           value="<?= $v('design_card_min') ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Smaller means more columns. 17 gives about four on a laptop.</span>
                </label>
                <label>
                    <span class="rs-label">Narrowest card, compact view</span>
                    <input type="number" name="design_card_min_dense" class="rs-input num" min="8" max="24" step="0.5"
                           value="<?= $v('design_card_min_dense') ?>" <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label class="sm:col-span-2">
                    <span class="rs-label">Image shape</span>
                    <input type="number" name="design_card_ratio" class="rs-input num" min="0.4" max="2" step="0.01"
                           value="<?= $v('design_card_ratio') ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Width ÷ height. 1 is square; 0.82 is the portrait crop in the design.</span>
                </label>
            </div>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- ============================ palette =========================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Colour</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php foreach ([
                    'design_color_deep'        => 'Header and footer',
                    'design_color_primary'     => 'Primary buttons',
                    'design_color_accent'      => 'Gold accent',
                    'design_color_surface'     => 'Page background',
                    'design_color_surface_alt' => 'Alternate band',
                ] as $key => $label): ?>
                    <label>
                        <span class="rs-label"><?= esc($label) ?></span>
                        <span class="flex items-center gap-2">
                            <input type="color" class="h-9 w-12 shrink-0 border border-shell-line"
                                   value="<?= $v($key) ?>" aria-hidden="true" tabindex="-1"
                                   oninput="this.nextElementSibling.value=this.value.toUpperCase()"
                                   <?= $canManage ? '' : 'disabled' ?>>
                            <input type="text" name="<?= $key ?>" class="rs-input num font-mono text-xs"
                                   maxlength="9" value="<?= $v($key) ?>" <?= $canManage ? '' : 'disabled' ?>>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ========================== the rest =========================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Detail</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Corner rounding (px)</span>
                    <input type="number" name="design_radius" class="rs-input num" min="0" max="32" step="1"
                           value="<?= $v('design_radius') ?>" <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Button rounding (px)</span>
                    <input type="number" name="design_pill_radius" class="rs-input num" min="0" max="999" step="1"
                           value="<?= $v('design_pill_radius') ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">999 gives the full pill.</span>
                </label>
            </div>

            <label class="mt-5 flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="design_sticky_header" value="1" class="accent-mulberry"
                       <?= ($values['design_sticky_header'] ?? '1') === '1' ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                <span>Header follows the page as it scrolls</span>
            </label>
            <label class="mt-3 flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="design_marquee" value="1" class="accent-mulberry"
                       <?= ($values['design_marquee'] ?? '1') === '1' ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                <span>Show the promise strip under the hero</span>
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Promise strip wording</span>
                <input type="text" name="design_marquee_text" class="rs-input" maxlength="500"
                       value="<?= $v('design_marquee_text') ?>" <?= $canManage ? '' : 'disabled' ?>>
                <span class="rs-help">Separate each phrase with a middle dot.</span>
            </label>
        </section>
    </div>

    <!-- ============================== menu =============================== -->
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Main menu</h2>
        <label class="mt-4 block max-w-xl">
            <span class="rs-label">One item per line</span>
            <textarea name="design_nav" class="rs-textarea font-mono text-xs" rows="7"
                      <?= $canManage ? '' : 'disabled' ?>><?= esc(old('design_nav') ?? $values['design_nav'] ?? '') ?></textarea>
            <span class="rs-help">
                Written as <code class="font-mono">Label | /path</code>. Only paths on this site are
                accepted — an editable menu that took full web addresses would be a way to point
                your own navigation somewhere else.
            </span>
        </label>
    </section>

    <?php if ($canManage): ?>
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rs-btn rs-btn--primary">Save appearance</button>
            <span class="rs-help">Changes appear on the next storefront page load.</span>
        </div>
    <?php else: ?>
        <p class="rs-help">Your role can view these but not change them.</p>
    <?php endif; ?>
</form>

<?php if ($canManage): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/appearance/reset') ?>"
              data-confirm="Put the appearance back to the original design?"
              data-confirm-detail="Every setting on this screen returns to how the site shipped. Nothing else is affected."
              data-confirm-action="Reset it">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Reset to the original design</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
