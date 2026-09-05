<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * The sign-in screen's wording and its Google credentials.
 *
 * @var array<int, string> $copy
 * @var array<string, array<string, string>> $values
 * @var bool $hasSecret
 */
$v = static fn (string $k): string => esc((string) (old($k) ?? ($values[$k]['value'] ?? '')), 'attr');
$l = static fn (string $k): string => (string) ($values[$k]['label'] ?? $k);
$long = ['search_placeholders', 'auth_login_body', 'auth_register_body', 'auth_panel_body', 'auth_code_body', 'auth_details_body'];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Settings',
    'heading'    => 'Storefront wording',
    'subheading' => 'The search box, the corporate switch, and the sign-in screens.',
    'actions'    => '<a href="' . site_url('account/login') . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View it</a>',
]) ?>

<form method="post" action="<?= site_url('admin/auth') ?>" class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">

        <!-- ============================== wording ======================== -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Wording</h2>
            <p class="rs-help mt-2">Leave any of these blank to fall back to the built-in text.</p>

            <div class="mt-5 grid gap-4">
                <?php foreach ($copy as $key): ?>
                    <label>
                        <span class="rs-label"><?= esc($l($key)) ?></span>
                        <?php if (in_array($key, $long, true)): ?>
                            <textarea name="<?= esc($key, 'attr') ?>" class="rs-textarea" rows="2"
                                      maxlength="255"><?= esc(old($key) ?? ($values[$key]['value'] ?? '')) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?= esc($key, 'attr') ?>" class="rs-input"
                                   maxlength="191" value="<?= $v($key) ?>">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================== google ========================= -->
        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Sign in with Google</h2>

                <p class="rs-help mt-2">
                    <?php if ($configured): ?>
                        <span class="text-good">Connected.</span> The button shows on the sign-in screen.
                    <?php else: ?>
                        <span class="text-brass">Not connected.</span> The button stays hidden until both
                        fields below are filled in.
                    <?php endif; ?>
                </p>

                <label class="mt-4 block">
                    <span class="rs-label">Client ID</span>
                    <input type="text" name="google_auth_client_id" class="rs-input font-mono text-xs"
                           maxlength="255" autocomplete="off"
                           placeholder="000000-abc.apps.googleusercontent.com"
                           value="<?= $v('google_auth_client_id') ?>">
                </label>

                <label class="mt-4 block">
                    <span class="rs-label">Client secret</span>
                    <?php /* Never rendered back. Only whether one exists. */ ?>
                    <input type="password" name="google_auth_client_secret" class="rs-input font-mono text-xs"
                           maxlength="255" autocomplete="new-password"
                           placeholder="<?= $hasSecret ? 'Stored — type to replace' : 'Paste the secret' ?>">
                    <span class="rs-help">
                        <?= $hasSecret
                            ? 'A secret is stored, encrypted. Leave this blank to keep it.'
                            : 'Stored encrypted. It is never shown again once saved.' ?>
                    </span>
                </label>

                <?php if ($hasSecret): ?>
                    <label class="mt-3 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="forget_secret" value="1" class="accent-mulberry">
                        <span>Forget the stored secret</span>
                    </label>
                <?php endif; ?>

                <div class="mt-5 border-t border-shell-line pt-4">
                    <span class="rs-label">Redirect URI</span>
                    <p class="rs-help">Paste this into the Google Cloud console, exactly:</p>
                    <code class="mt-2 block break-all bg-shell-deep p-2 font-mono text-[0.6875rem]">
                        <?= esc($redirectUri) ?>
                    </code>
                </div>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">Save</button>
        </aside>
    </div>
</form>

<?= $this->endSection() ?>
