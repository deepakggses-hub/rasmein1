<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew    = $role === null;
$isAll    = in_array('*', $granted, true);
$v        = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$oldPerms = old('permissions');
$isSystem = ! $isNew && (int) ($role['is_system'] ?? 0) === 1;
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'People',
    'heading'    => $isNew ? 'New role' : $role['name'],
    'subheading' => $isNew
        ? 'Choose what this kind of account may do.'
        : $assigned . ' account' . ($assigned === 1 ? '' : 's') . ' currently hold this role.',
    'actions'    => '<a href="' . site_url('admin/roles') . '" class="rs-btn rs-btn--outline rs-btn--sm">All roles</a>',
]) ?>

<form method="post" action="<?= $isNew ? site_url('admin/roles') : site_url('admin/roles/' . $role['id']) ?>"
      class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <div class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">The role</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Name <span class="text-bad">*</span></span>
                        <input type="text" name="name" class="rs-input" required maxlength="80"
                               placeholder="Warehouse" value="<?= $v('name', $role['name'] ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Slug</span>
                        <input type="text" name="slug" class="rs-input font-mono text-xs" maxlength="80"
                               value="<?= $v('slug', $role['slug'] ?? '') ?>"
                               <?= $isSystem ? 'disabled' : '' ?>>
                        <?php if ($isSystem): ?>
                            <span class="rs-help">Fixed — the code refers to this built-in role by slug.</span>
                        <?php endif; ?>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">What it is for</span>
                        <input type="text" name="description" class="rs-input" maxlength="255"
                               placeholder="Packs and dispatches orders, no access to money"
                               value="<?= $v('description', $role['description'] ?? '') ?>">
                    </label>
                </div>
            </section>

            <!-- ------------------------------------------- permissions -->
            <section class="border border-shell-line bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">What it allows</h2>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="rs-link text-ink-muted" data-perm-all>Select all</button>
                        <span class="text-shell-line">·</span>
                        <button type="button" class="rs-link text-ink-muted" data-perm-none>Clear</button>
                    </div>
                </div>

                <?php if ($isSuper): ?>
                    <label class="flex items-start gap-3 border-b border-shell-line bg-brass-soft/25 px-5 py-4">
                        <input type="checkbox" name="permissions[]" value="*" class="mt-0.5 accent-mulberry"
                               data-perm-wildcard <?= $isAll ? 'checked' : '' ?>>
                        <span>
                            <span class="text-sm font-semibold">Everything, now and in future</span>
                            <span class="rs-help">
                                A full administrator. Also covers permissions added by later updates,
                                which is why it is worth having exactly one such role.
                            </span>
                        </span>
                    </label>
                <?php endif; ?>

                <div data-perm-groups class="divide-y divide-shell-line <?= $isAll ? 'opacity-40' : '' ?>">
                    <?php foreach ($catalogue as $group => $permissions): ?>
                        <?php
                        $available = array_filter(array_keys($permissions), static fn (string $p): bool => in_array($p, $grantable, true));
                        if ($available === []) { continue; }
                        ?>
                        <fieldset class="px-5 py-4">
                            <legend class="rs-eyebrow rs-eyebrow--plain"><?= esc($group) ?></legend>
                            <ul class="mt-3 grid gap-2.5 sm:grid-cols-2">
                                <?php foreach ($permissions as $permission => [$label, $description]): ?>
                                    <?php
                                    $allowed = in_array($permission, $grantable, true);
                                    $on = $oldPerms !== null
                                        ? in_array($permission, (array) $oldPerms, true)
                                        : in_array($permission, $granted, true);
                                    ?>
                                    <li>
                                        <label class="flex items-start gap-2.5 <?= $allowed ? '' : 'opacity-40' ?>">
                                            <input type="checkbox" name="permissions[]"
                                                   value="<?= esc($permission, 'attr') ?>"
                                                   class="mt-0.5 accent-mulberry" data-perm
                                                   <?= $on ? 'checked' : '' ?>
                                                   <?= $allowed ? '' : 'disabled' ?>>
                                            <span>
                                                <span class="block text-sm font-medium"><?= esc($label) ?></span>
                                                <span class="block text-xs text-ink-muted"><?= esc($description) ?></span>
                                                <?php if (! $allowed): ?>
                                                    <span class="block text-xs text-warn">Your account does not hold this.</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Chosen</h2>
                <p class="num mt-2 font-display text-3xl font-semibold text-mulberry" data-perm-count>0</p>
                <p class="rs-help">permissions selected</p>

                <?php if ($isOwnRole): ?>
                    <p class="mt-4 border-l-2 border-warn bg-brass-soft/25 px-3 py-2 text-xs text-ink-soft">
                        This is the role your own account holds. Removing a permission here
                        removes it from you as well, on your next page load.
                    </p>
                <?php endif; ?>

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                    <?= $isNew ? 'Create role' : 'Save role' ?>
                </button>
            </section>

            <?php if (! $isNew): ?>
                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Held by</h2>
                    <p class="num mt-2 text-2xl font-semibold"><?= (int) $assigned ?></p>
                    <p class="rs-help">account<?= $assigned === 1 ? '' : 's' ?></p>
                    <a href="<?= site_url('admin/staff') ?>" class="rs-link mt-3 block text-sm">Manage staff</a>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</form>

<?php if (! $isNew && ! $isSystem): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/roles/' . $role['id'] . '/delete') ?>"
              data-confirm="Remove the role &ldquo;<?= esc($role['name'], 'attr') ?>&rdquo;?"
              data-confirm-detail="<?= $assigned > 0
                  ? esc($assigned . ' account(s) still hold it, so this will be refused.', 'attr')
                  : 'No accounts hold it, so nothing will lose access.' ?>"
              data-confirm-action="Remove the role">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this role</button>
        </form>
    </div>
<?php elseif ($isSystem): ?>
    <div class="px-5 pb-8 lg:px-8">
        <p class="rs-help">This is a built-in role. Its permissions can be changed, but it cannot be removed.</p>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
