<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$v = static fn (string $k) => esc((string) (old($k) ?? $values[$k] ?? ''), 'attr');

$hints = [
    'brand_logo'       => ['Storefront header', 'Transparent PNG or WebP, around 320×80. Blank keeps the Rasmein wordmark.'],
    'brand_logo_light' => ['Footer and admin bar', 'A version that reads on a dark background. Falls back to the main logo.'],
    'brand_favicon'    => ['Browser tab', 'Square PNG, at least 180×180.'],
    'brand_og_image'   => ['WhatsApp and social previews', '1200×630 is the size every platform accepts.'],
];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'System',
    'heading'    => 'Shop identity',
    'subheading' => 'Logo, favicon, contact details and the other things that appear across the site.',
]) ?>

<form method="post" action="<?= site_url('admin/brand') ?>" enctype="multipart/form-data"
      class="space-y-6 px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <!-- ============================== images ============================= -->
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Images</h2>
        <p class="rs-help mt-2 max-w-2xl">
            Every upload is re-encoded on the server, which also strips any location
            data a camera recorded. JPEG, PNG or WebP.
        </p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($images as $field => $label): ?>
                <?php $current = $values[$field] ?? ''; ?>
                <div class="border border-shell-line p-4">
                    <p class="text-sm font-semibold"><?= esc($label) ?></p>
                    <p class="rs-help"><?= esc($hints[$field][0] ?? '') ?></p>

                    <div class="mt-3 flex h-24 items-center justify-center border border-shell-line
                                <?= $field === 'brand_logo_light' ? 'bg-ink' : 'bg-shell-deep' ?>">
                        <?php if ($current !== ''): ?>
                            <img src="<?= rs_url($current) ?>" alt="<?= esc($label, 'attr') ?>"
                                 class="max-h-20 max-w-full object-contain">
                        <?php else: ?>
                            <span class="font-mono text-[0.625rem] tracking-widest text-ink-muted uppercase">Not set</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($canManage): ?>
                        <label class="mt-3 block">
                            <span class="sr-only">Upload <?= esc($label) ?></span>
                            <input type="file" name="<?= esc($field, 'attr') ?>" class="rs-input text-xs"
                                   accept="image/jpeg,image/png,image/webp">
                        </label>
                        <?php if ($current !== ''): ?>
                            <label class="mt-2 flex items-center gap-2 text-xs">
                                <input type="checkbox" name="remove_<?= esc($field, 'attr') ?>" value="1" class="accent-mulberry">
                                <span class="text-ink-muted">Remove this image</span>
                            </label>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="rs-help mt-2"><?= esc($hints[$field][1] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- ============================ the shop ========================= -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">The shop</h2>
            <div class="mt-4 grid gap-4">
                <label>
                    <span class="rs-label">Shop name</span>
                    <input type="text" name="store_name" class="rs-input" maxlength="120" value="<?= $v('store_name') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Used in page titles, emails and the footer.</span>
                </label>
                <label>
                    <span class="rs-label">Tagline</span>
                    <input type="text" name="store_tagline" class="rs-input" maxlength="191" value="<?= $v('store_tagline') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Support email</span>
                    <input type="email" name="support_email" class="rs-input" maxlength="191" value="<?= $v('support_email') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Where customers are told to write. Separate from the address mail is sent from.</span>
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Support phone</span>
                        <input type="tel" name="support_phone" class="rs-input" maxlength="40" value="<?= $v('support_phone') ?>"
                               <?= $canManage ? '' : 'disabled' ?>>
                    </label>
                    <label>
                        <span class="rs-label">WhatsApp number</span>
                        <input type="tel" name="whatsapp_number" class="rs-input" maxlength="40"
                               placeholder="919876543210" value="<?= $v('whatsapp_number') ?>"
                               <?= $canManage ? '' : 'disabled' ?>>
                        <span class="rs-help">Country code, no plus or spaces.</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- ========================= search & sharing ==================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Search and sharing</h2>
            <div class="mt-4 grid gap-4">
                <label>
                    <span class="rs-label">Title suffix</span>
                    <input type="text" name="meta_title_suffix" class="rs-input" maxlength="80"
                           placeholder="· Rasmein" value="<?= $v('meta_title_suffix') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Added to the end of page titles. Blank uses the shop name.</span>
                </label>
                <label>
                    <span class="rs-label">Default description</span>
                    <textarea name="meta_description" class="rs-textarea" rows="3" maxlength="255"
                              <?= $canManage ? '' : 'disabled' ?>><?= esc(old('meta_description') ?? $values['meta_description'] ?? '') ?></textarea>
                    <span class="rs-help">Shown in search results on pages with nothing more specific. Around 150 characters.</span>
                </label>
            </div>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- ============================== social ======================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Social links</h2>
            <p class="rs-help mt-2">Full web addresses. Anything left blank simply does not appear.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php foreach ([
                    'social_instagram' => 'Instagram',
                    'social_facebook'  => 'Facebook',
                    'social_whatsapp'  => 'WhatsApp link',
                    'social_youtube'   => 'YouTube',
                    'social_pinterest' => 'Pinterest',
                    'social_linkedin'  => 'LinkedIn',
                ] as $key => $label): ?>
                    <label>
                        <span class="rs-label"><?= esc($label) ?></span>
                        <input type="url" name="<?= esc($key, 'attr') ?>" class="rs-input text-xs"
                               placeholder="https://" maxlength="255" value="<?= $v($key) ?>"
                               <?= $canManage ? '' : 'disabled' ?>>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================== legal ========================= -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Business details</h2>
            <p class="rs-help mt-2">Appears in the footer and on invoices.</p>
            <div class="mt-4 grid gap-4">
                <label>
                    <span class="rs-label">Registered business name</span>
                    <input type="text" name="legal_name" class="rs-input" maxlength="191" value="<?= $v('legal_name') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">If different from the shop name.</span>
                </label>
                <label>
                    <span class="rs-label">GSTIN</span>
                    <input type="text" name="legal_gstin" class="rs-input num font-mono" maxlength="20" value="<?= $v('legal_gstin') ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Registered address</span>
                    <textarea name="legal_address" class="rs-textarea" rows="3" maxlength="500"
                              <?= $canManage ? '' : 'disabled' ?>><?= esc(old('legal_address') ?? $values['legal_address'] ?? '') ?></textarea>
                </label>
            </div>
        </section>
    </div>

    <?php if ($canManage): ?>
        <button type="submit" class="rs-btn rs-btn--primary">Save shop identity</button>
    <?php else: ?>
        <p class="rs-help">Your role can view these but not change them.</p>
    <?php endif; ?>
</form>

<?= $this->endSection() ?>
