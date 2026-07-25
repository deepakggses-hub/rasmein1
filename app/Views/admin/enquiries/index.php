<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Sales',
    'heading'    => 'Enquiries',
    'subheading' => $total . ' lead' . ($total === 1 ? '' : 's') . ' matching.',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <form method="get" class="flex flex-wrap items-end gap-3 border border-shell-line bg-white p-4">
        <label class="min-w-52 flex-1">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input" placeholder="Reference, name, email or company"
                   value="<?= esc($filters['q'] ?? '', 'attr') ?>">
        </label>
        <label>
            <span class="rs-label">Stage</span>
            <select name="status" class="rs-select w-auto">
                <option value="">Any</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="flex items-center gap-2 pb-2.5 text-sm">
            <input type="checkbox" name="overdue" value="1" class="accent-mulberry" <?= $filters['overdue'] ? 'checked' : '' ?>>
            <span>Follow-up overdue</span>
        </label>
        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Filter</button>
        <a href="<?= site_url('admin/enquiries') ?>" class="rs-btn rs-btn--outline rs-btn--sm">Clear</a>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($enquiries === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No enquiries match that.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Reference</th>
                        <th class="px-4 py-2.5">Received</th>
                        <th class="px-4 py-2.5">Contact</th>
                        <th class="px-4 py-2.5">Stage</th>
                        <th class="px-4 py-2.5">Owner</th>
                        <th class="px-4 py-2.5">Follow up</th>
                        <th class="num px-4 py-2.5 text-right">Estimate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($enquiries as $row): ?>
                        <?php $overdue = $row['followup_at'] !== null
                            && strtotime((string) $row['followup_at']) < time()
                            && ! in_array($row['lead_status'], ['won', 'lost', 'spam'], true); ?>
                        <tr class="hover:bg-shell">
                            <td class="num px-4 py-3">
                                <a href="<?= site_url('admin/enquiries/' . $row['id']) ?>" class="rs-link font-medium">
                                    <?= esc($row['enquiry_ref']) ?>
                                </a>
                            </td>
                            <td class="num px-4 py-3 text-ink-muted">
                                <?= esc(date('j M Y', strtotime((string) $row['placed_at']))) ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block"><?= esc(rs_excerpt($row['customer_name'], 20)) ?></span>
                                <span class="block text-xs text-ink-muted">
                                    <?= esc($row['company'] ?: $row['customer_phone']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rs-badge <?= $row['lead_status'] === 'won' ? 'rs-badge--enquire' : ($row['lead_status'] === 'new' ? 'rs-badge--brass' : 'rs-badge--soft') ?>">
                                    <?= esc($statuses[$row['lead_status']] ?? $row['lead_status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-muted"><?= esc($row['owner_name'] ?? '—') ?></td>
                            <td class="num px-4 py-3 <?= $overdue ? 'font-semibold text-bad' : 'text-ink-muted' ?>">
                                <?= $row['followup_at'] !== null
                                    ? esc(date('j M', strtotime((string) $row['followup_at'])))
                                    : '—' ?>
                            </td>
                            <td class="num px-4 py-3 text-right font-medium">
                                <?= rs_money($row['quoted_value'] ?? $row['estimated_value'] ?? 0) ?>
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
