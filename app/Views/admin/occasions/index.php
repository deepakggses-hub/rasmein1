<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php $now = time(); ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Occasions',
    'subheading' => 'Diwali, weddings, Raksha Bandhan. Tag products to one and it gets its own page.',
    'actions'    => '<a href="' . site_url('admin/occasions/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New occasion</a>',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($occasions === []): ?>
            <p class="px-4 py-10 text-center text-sm text-ink-muted">
                No occasions yet.
                <a href="<?= site_url('admin/occasions/new') ?>" class="rs-link text-mulberry">Create the first one</a>.
            </p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Occasion</th>
                        <th class="px-4 py-2.5">Web address</th>
                        <th class="num px-4 py-2.5 text-right">Products</th>
                        <th class="px-4 py-2.5">Runs</th>
                        <th class="px-4 py-2.5">Showing</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($occasions as $occasion): ?>
                        <?php
                        $started = $occasion['starts_at'] === null || strtotime((string) $occasion['starts_at']) <= $now;
                        $ended   = $occasion['ends_at'] !== null && strtotime((string) $occasion['ends_at']) < $now;
                        $live    = $occasion['is_active'] && $started && ! $ended;
                        $count   = (int) ($counts[(int) $occasion['id']] ?? 0);
                        ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="block h-9 w-9 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= rs_url(rs_image($occasion['image'] ?? null, 'products')) ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <a href="<?= site_url('admin/occasions/' . $occasion['id'] . '/edit') ?>"
                                       class="rs-link font-medium"><?= esc($occasion['name']) ?></a>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="<?= rs_url((string) $occasion['slug']) ?>" target="_blank" rel="noopener"
                                   class="rs-link font-mono text-xs text-ink-muted">/<?= esc($occasion['slug']) ?></a>
                            </td>
                            <td class="num px-4 py-2.5 text-right <?= $count === 0 ? 'text-bad' : '' ?>">
                                <?= $count ?>
                                <?php if ($count === 0): ?>
                                    <span class="block text-[0.625rem]">nothing tagged</span>
                                <?php endif; ?>
                            </td>
                            <td class="num px-4 py-2.5 text-xs text-ink-muted">
                                <?= $occasion['starts_at'] !== null ? esc(date('j M y', strtotime((string) $occasion['starts_at']))) : 'any time' ?>
                                &ndash;
                                <?= $occasion['ends_at'] !== null ? esc(date('j M y', strtotime((string) $occasion['ends_at']))) : 'open' ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <?php if (! $occasion['is_active']): ?>
                                    <span class="rs-badge rs-badge--out">Off</span>
                                <?php elseif ($ended): ?>
                                    <span class="rs-badge rs-badge--out">Finished</span>
                                <?php elseif (! $started): ?>
                                    <span class="rs-badge rs-badge--soft">Scheduled</span>
                                <?php else: ?>
                                    <span class="rs-badge rs-badge--enquire">Live now</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="<?= site_url('admin/occasions/' . $occasion['id'] . '/edit') ?>"
                                   class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p class="rs-help mt-3 max-w-2xl">
        An occasion outside its dates is hidden rather than shown empty — a Diwali page
        in March is worse than nothing. You can also tag occasions from the product
        screen, which is quicker when a new product belongs to several.
    </p>
</div>

<?= $this->endSection() ?>
