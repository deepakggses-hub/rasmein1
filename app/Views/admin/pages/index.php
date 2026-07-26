<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Pages',
    'subheading' => 'About, shipping, returns and anything else customers need to read.',
    'actions'    => '<a href="' . site_url('admin/pages/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New page</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($pages === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No pages yet.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Title</th>
                        <th class="px-4 py-2.5">URL</th>
                        <th class="px-4 py-2.5">Footer</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($pages as $page): ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <a href="<?= site_url('admin/pages/' . $page['id'] . '/edit') ?>" class="rs-link font-medium">
                                    <?= esc($page['title']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-muted">
                                <a href="<?= site_url('page/' . $page['slug']) ?>" class="rs-link" target="_blank" rel="noopener">
                                    /page/<?= esc($page['slug']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-2.5"><?= $page['show_in_footer'] ? 'Yes' : '—' ?></td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge <?= $page['is_active'] ? 'rs-badge--soft' : 'rs-badge--out' ?>">
                                    <?= $page['is_active'] ? 'Live' : 'Hidden' ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="<?= site_url('admin/pages/' . $page['id'] . '/edit') ?>"
                                   class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
