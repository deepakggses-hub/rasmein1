<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'System',
    'heading'    => 'Audit log',
    'subheading' => $total . ' entr' . ($total === 1 ? 'y' : 'ies') . '. Append-only — entries are never edited or removed.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label>
            <span class="rs-label">Module</span>
            <select name="module" class="rs-select w-auto">
                <option value="">Everything</option>
                <?php foreach ($modules as $name): ?>
                    <option value="<?= esc($name, 'attr') ?>" <?= $module === $name ? 'selected' : '' ?>>
                        <?= esc(ucfirst($name)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Filter</button>
        <a href="<?= site_url('admin/audit') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($entries === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">Nothing logged for that.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">When</th>
                        <th class="px-4 py-2.5">Who</th>
                        <th class="px-4 py-2.5">Action</th>
                        <th class="px-4 py-2.5">Detail</th>
                        <th class="px-4 py-2.5">Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($entries as $entry): ?>
                        <tr class="hover:bg-shell align-top">
                            <td class="num px-4 py-2.5 whitespace-nowrap text-ink-muted">
                                <?= esc(date('j M y, H:i', strtotime((string) $entry['created_at']))) ?>
                            </td>
                            <td class="px-4 py-2.5"><?= esc($entry['admin_name'] ?? 'System') ?></td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge rs-badge--soft"><?= esc($entry['module']) ?></span>
                                <span class="ml-1.5"><?= esc(str_replace('_', ' ', $entry['action'])) ?></span>
                            </td>
                            <td class="px-4 py-2.5 text-ink-muted"><?= esc(rs_excerpt($entry['summary'] ?? '', 50)) ?></td>
                            <td class="px-4 py-2.5">
                                <?php if (! empty($entry['old_values']) || ! empty($entry['new_values'])): ?>
                                    <details>
                                        <summary class="cursor-pointer font-mono text-[0.625rem] text-brass">diff</summary>
                                        <?php if (! empty($entry['old_values'])): ?>
                                            <p class="mt-1 font-mono text-[0.625rem] break-all text-bad">
                                                &minus; <?= esc(rs_excerpt($entry['old_values'], 160)) ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (! empty($entry['new_values'])): ?>
                                            <p class="mt-0.5 font-mono text-[0.625rem] break-all text-pista-deep">
                                                + <?= esc(rs_excerpt($entry['new_values'], 160)) ?>
                                            </p>
                                        <?php endif; ?>
                                    </details>
                                <?php else: ?>
                                    <span class="text-ink-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= view('admin/partials/pagination', ['pager' => $pager]) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
