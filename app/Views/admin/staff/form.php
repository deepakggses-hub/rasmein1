<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $user === null;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'People',
    'heading'    => $isNew ? 'New staff account' : $user['name'],
    'subheading' => $isSelf ? 'This is your own account — some controls are locked to stop a lockout.' : null,
    'actions'    => '<a href="' . site_url('admin/staff') . '" class="rs-btn rs-btn--outline rs-btn--sm">All staff</a>',
]) ?>

<form method="post" action="<?= $isNew ? site_url('admin/staff') : site_url('admin/staff/' . $user['id']) ?>"
      class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Details</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">Name <span class="text-bad">*</span></span>
                    <input type="text" name="name" class="rs-input" required maxlength="120"
                           value="<?= $v('name', $user['name'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Email <span class="text-bad">*</span></span>
                    <input type="email" name="email" class="rs-input" required maxlength="191"
                           autocomplete="off" value="<?= $v('email', $user['email'] ?? '') ?>">
                </label>
                <label>
                    <span class="rs-label">Phone</span>
                    <input type="tel" name="phone" class="rs-input" maxlength="20"
                           value="<?= $v('phone', $user['phone'] ?? '') ?>">
                </label>
            </div>

            <hr class="rs-rule my-6">

            <h2 class="rs-eyebrow rs-eyebrow--plain">Password</h2>
            <label class="mt-4 block max-w-sm">
                <span class="rs-label">
                    <?= $isNew ? 'Starting password' : 'Set a new password' ?>
                    <?= $isNew ? '<span class="text-bad">*</span>' : '<span class="text-ink-muted">(optional)</span>' ?>
                </span>
                <input type="password" name="password" class="rs-input" minlength="10"
                       autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                <span class="rs-help">
                    At least 10 characters. Whatever you set here is single-use — they
                    will be asked to replace it the first time they sign in, so you never
                    hold a password that still works.
                </span>
            </label>
        </section>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Access</h2>

                <label class="mt-4 block">
                    <span class="rs-label">Role <span class="text-bad">*</span></span>
                    <select name="role_id" class="rs-select" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>"
                                <?= (int) (old('role_id') ?? $user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                                <?= esc($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf): ?>
                        <input type="hidden" name="role_id" value="<?= (int) $user['role_id'] ?>">
                        <span class="rs-help">You cannot change your own role.</span>
                    <?php elseif (count($roles) < count($allRoles)): ?>
                        <span class="rs-help">
                            Only roles within your own access are listed —
                            <?= count($allRoles) - count($roles) ?> hidden.
                        </span>
                    <?php endif; ?>
                </label>

                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $user['is_active']) ? 'checked' : '' ?>
                           <?= $isSelf ? 'disabled' : '' ?>>
                    <span>Can sign in</span>
                </label>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="is_active" value="1">
                    <span class="rs-help">You cannot disable your own account.</span>
                <?php endif; ?>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create account' : 'Save account' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew && ! $isSelf): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/staff/' . $user['id'] . '/delete') ?>"
              onsubmit="return confirm('Remove this account? Their audit history stays.');">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this account</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
