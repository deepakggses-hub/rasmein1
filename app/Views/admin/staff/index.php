<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'People',
    'heading'    => 'Staff',
    'subheading' => 'Who can sign in, and what each of them may do.',
    'actions'    => '<a href="' . site_url('admin/staff/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New account</a>',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <table class="w-full min-w-3xl text-sm">
            <thead class="border-b border-shell-line bg-shell-deep text-left">
                <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Email</th>
                    <th class="px-4 py-2.5">Role</th>
                    <th class="px-4 py-2.5">Last signed in</th>
                    <th class="px-4 py-2.5">State</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-shell-line">
                <?php foreach ($staff as $person): ?>
                    <?php $isSelf = (int) $person['id'] === (int) session('admin_id'); ?>
                    <tr class="hover:bg-shell">
                        <td class="px-4 py-2.5">
                            <a href="<?= site_url('admin/staff/' . $person['id'] . '/edit') ?>" class="rs-link font-medium">
                                <?= esc($person['name']) ?>
                            </a>
                            <?php if ($isSelf): ?>
                                <span class="rs-badge rs-badge--brass ml-1">You</span>
                            <?php endif; ?>
                            <?php if ((int) $person['must_change_password'] === 1): ?>
                                <span class="rs-badge rs-badge--soft ml-1">Must set password</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-ink-muted"><?= esc($person['email']) ?></td>
                        <td class="px-4 py-2.5"><?= esc($person['role_name'] ?? '—') ?></td>
                        <td class="num px-4 py-2.5 text-xs text-ink-muted">
                            <?= $person['last_login_at'] !== null
                                ? esc(date('j M y, H:i', strtotime((string) $person['last_login_at'])))
                                : 'never' ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="rs-badge <?= $person['is_active'] ? 'rs-badge--enquire' : 'rs-badge--out' ?>">
                                <?= $person['is_active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="<?= site_url('admin/staff/' . $person['id'] . '/edit') ?>"
                               class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Roles are shown read-only: what each grants, so choosing one is informed. -->
    <section>
        <h2 class="rs-eyebrow">Roles</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <?php foreach ($roles as $role): ?>
                <?php $granted = json_decode((string) $role['permissions'], true) ?: []; ?>
                <div class="border border-shell-line bg-white p-4">
                    <p class="font-semibold"><?= esc($role['name']) ?></p>
                    <p class="rs-help mt-1"><?= esc($role['description'] ?? '') ?></p>
                    <p class="mt-3">
                        <?php if (in_array('*', $granted, true)): ?>
                            <span class="rs-badge rs-badge--brass">Everything</span>
                        <?php else: ?>
                            <span class="num font-mono text-[0.625rem] text-ink-muted">
                                <?= count($granted) ?> permission<?= count($granted) === 1 ? '' : 's' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
