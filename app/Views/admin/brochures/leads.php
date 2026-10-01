<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Who downloaded what.
 *
 * @var array<int, array<string, mixed>> $leads
 * @var array<int, array<string, mixed>> $brochures
 * @var array<string, mixed>             $filters
 */
$query = array_filter([
    'q'        => $filters['q'] ?? '',
    'brochure' => ($filters['brochure'] ?? 0) ?: '',
], static fn ($v): bool => (string) $v !== '');
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Brochure leads',
    'subheading' => 'Everyone who asked for a brochure, newest first.',
    'actions'    => '<a href="' . site_url('admin/brochures') . '" class="rs-btn rs-btn--outline rs-btn--sm">Brochures</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">

    <form method="get" class="flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="rs-label">Search</span>
            <input type="search" name="q" class="rs-input w-64" placeholder="Name, email or phone"
                   value="<?= esc($filters['q'] ?? '', 'attr') ?>">
        </label>

        <label class="block">
            <span class="rs-label">Brochure</span>
            <select name="brochure" class="rs-input w-56">
                <option value="">All brochures</option>
                <?php foreach ($brochures as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"
                        <?= (int) ($filters['brochure'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= esc($b['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Filter</button>

        <?php if ($leads !== []): ?>
            <?php /* Built here from a filtered array, so it is safe to output raw —
                     esc(..., 'attr') would encode ? and = and make it unreadable. */ ?>
            <a class="rs-btn rs-btn--outline rs-btn--sm"
               href="<?= site_url('admin/brochures/leads/export') . ($query === [] ? '' : '?' . http_build_query($query)) ?>">
                Export CSV
            </a>
        <?php endif; ?>

        <span class="ml-auto self-center font-mono text-xs text-ink-muted">
            <?= number_format($total) ?> lead<?= $total === 1 ? '' : 's' ?>
        </span>
    </form>

    <div class="mt-5 overflow-x-auto border border-shell-line bg-white">
        <?php if ($leads === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-muted">
                <?= $filters['q'] !== '' || ($filters['brochure'] ?? 0) > 0
                    ? 'Nothing matches that.'
                    : 'No brochure downloads yet.' ?>
            </p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">When</th>
                        <th class="px-4 py-2.5">Who</th>
                        <th class="px-4 py-2.5">Brochure</th>
                        <th class="px-4 py-2.5">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($leads as $lead): ?>
                        <tr class="hover:bg-shell align-top">
                            <td class="num px-4 py-2.5 text-xs whitespace-nowrap text-ink-muted">
                                <?= esc(date('j M Y', strtotime((string) $lead['created_at']))) ?>
                                <span class="block"><?= esc(date('H:i', strtotime((string) $lead['created_at']))) ?></span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium"><?= esc($lead['name']) ?></span>
                                <?php if ($lead['customer_id'] !== null): ?>
                                    <span class="rs-badge rs-badge--brass">Account</span>
                                <?php endif; ?>
                                <?php /* Email is optional now, so NULL is a normal value here.
                                         An empty `mailto:` is a link that opens a blank
                                         message to nobody — worse than saying there is
                                         none. Phone is the one that is always present. */ ?>
                                <p class="num text-xs"><?= esc($lead['phone']) ?></p>
                                <p class="text-xs">
                                    <?php if (! empty($lead['email'])): ?>
                                        <a class="rs-link" href="mailto:<?= esc($lead['email'], 'attr') ?>"><?= esc($lead['email']) ?></a>
                                    <?php else: ?>
                                        <span class="text-ink-muted">no email given</span>
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td class="px-4 py-2.5">
                                <?php /* The SNAPSHOT, not the current title. The brochure may be
                                         gone, and what they asked for does not change when it is. */ ?>
                                <?= esc($lead['brochure_title']) ?>
                                <?php if ($lead['brochure_id'] === null): ?>
                                    <span class="block text-xs text-ink-muted">brochure since removed</span>
                                <?php endif; ?>
                                <?php if (! empty($lead['source_type'])): ?>
                                    <span class="block font-mono text-xs text-ink-muted">
                                        from <?= esc($lead['source_type']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-ink-soft">
                                <?= $lead['notes'] !== null && $lead['notes'] !== ''
                                    ? nl2br(esc($lead['notes']))
                                    : '<span class="text-ink-muted">&mdash;</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-5 flex items-center justify-between font-mono text-xs uppercase">
            <?php $base = site_url('admin/brochures/leads'); ?>
            <?php if ($page > 1): ?>
                <a class="rs-link" href="<?= $base . '?' . http_build_query($query + ['page' => $page - 1]) ?>">Newer</a>
            <?php else: ?><span></span><?php endif; ?>

            <span class="text-ink-muted">Page <?= $page ?> of <?= $pages ?></span>

            <?php if ($page < $pages): ?>
                <a class="rs-link" href="<?= $base . '?' . http_build_query($query + ['page' => $page + 1]) ?>">Older</a>
            <?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
