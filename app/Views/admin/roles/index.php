<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'People',
    'heading'    => 'Roles',
    'subheading' => 'What each kind of staff account is allowed to do.',
    'actions'    => '<a href="' . site_url('admin/roles/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New role</a>'
        . '<a href="' . site_url('admin/staff') . '" class="rs-btn rs-btn--outline rs-btn--sm">Staff</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <ul class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($roles as $role): ?>
            <?php
            $granted = json_decode((string) $role['permissions'], true) ?: [];
            $isAll   = in_array('*', $granted, true);
            $holders = $counts[(int) $role['id']] ?? 0;
            ?>
            <li class="rs-card flex flex-col bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-display text-lg font-semibold">
                            <a href="<?= site_url('admin/roles/' . $role['id'] . '/edit') ?>" class="rs-link">
                                <?= esc($role['name']) ?>
                            </a>
                        </h2>
                        <p class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                            <?= esc($role['slug']) ?>
                        </p>
                    </div>
                    <?php if ((int) ($role['is_system'] ?? 0) === 1): ?>
                        <span class="rs-badge rs-badge--soft shrink-0">Built in</span>
                    <?php endif; ?>
                </div>

                <?php if (! empty($role['description'])): ?>
                    <p class="mt-2 text-sm text-ink-muted"><?= esc($role['description']) ?></p>
                <?php endif; ?>

                <div class="mt-4">
                    <?php if ($isAll): ?>
                        <span class="rs-badge rs-badge--brass">Everything</span>
                    <?php else: ?>
                        <p class="num font-mono text-[0.625rem] tracking-[0.14em] text-ink-muted uppercase">
                            <?= count($granted) ?> of <?= count(config(\Config\Permissions::class)->all()) ?> permissions
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <?php foreach (array_slice($granted, 0, 6) as $permission): ?>
                                <span class="rs-badge rs-badge--soft"><?= esc(config(\Config\Permissions::class)->label($permission)) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($granted) > 6): ?>
                                <span class="rs-badge rs-badge--soft">+<?= count($granted) - 6 ?> more</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-auto flex items-center justify-between gap-3 border-t border-shell-line pt-4">
                    <p class="num text-sm text-ink-muted">
                        <?= $holders ?> account<?= $holders === 1 ? '' : 's' ?>
                    </p>
                    <a href="<?= site_url('admin/roles/' . $role['id'] . '/edit') ?>"
                       class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (! $isSuper): ?>
        <p class="rs-help mt-5 max-w-2xl">
            You can only grant permissions your own account holds, so some options are
            hidden when you edit a role. That is deliberate — it stops a role editor
            being a route to more access than it started with.
        </p>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
