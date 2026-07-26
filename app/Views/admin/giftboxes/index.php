<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Catalogue',
    'heading'    => 'Gift boxes',
    'subheading' => 'Capacity, what may go in, and how each box is priced.',
    'actions'    => $canManage
        ? '<a href="' . site_url('admin/gift-boxes/new') . '" class="rs-btn rs-btn--primary rs-btn--sm">New gift box</a>'
        : '',
]) ?>

<div class="px-5 py-6 lg:px-8">
    <div class="overflow-x-auto border border-shell-line bg-white">
        <?php if ($boxes === []): ?>
            <p class="px-4 py-8 text-sm text-ink-muted">No gift boxes yet.</p>
        <?php else: ?>
            <table class="w-full min-w-3xl text-sm">
                <thead class="border-b border-shell-line bg-shell-deep text-left">
                    <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5">Box</th>
                        <th class="num px-4 py-2.5 text-right">Box price</th>
                        <th class="num px-4 py-2.5 text-right">Fill</th>
                        <th class="num px-4 py-2.5 text-right">Can offer</th>
                        <th class="px-4 py-2.5">Journey</th>
                        <th class="px-4 py-2.5">State</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-shell-line">
                    <?php foreach ($boxes as $box): ?>
                        <?php $offers = $reach[(int) $box->id] ?? 0; ?>
                        <tr class="hover:bg-shell">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="block h-10 w-14 shrink-0 overflow-hidden bg-shell-deep">
                                        <img src="<?= esc($box->imageUrl(), 'attr') ?>" alt=""
                                             loading="lazy" class="h-full w-full object-cover">
                                    </span>
                                    <span>
                                        <?php if ($canManage): ?>
                                            <a href="<?= site_url('admin/gift-boxes/' . $box->id . '/edit') ?>" class="rs-link font-medium">
                                                <?= esc($box->name) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= esc($box->name) ?>
                                        <?php endif; ?>
                                        <?php if ($box->size_label): ?>
                                            <span class="rs-badge rs-badge--soft ml-1"><?= esc($box->size_label) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="num px-4 py-2.5 text-right"><?= esc($box->formattedBasePrice()) ?></td>
                            <td class="num px-4 py-2.5 text-right text-ink-muted">
                                <?= (int) $box->min_slots ?>–<?= (int) $box->capacity_slots ?>
                            </td>
                            <td class="num px-4 py-2.5 text-right <?= $offers === 0 ? 'font-semibold text-bad' : '' ?>">
                                <?= $offers ?>
                                <?php if ($offers === 0): ?>
                                    <span class="block text-[0.625rem]">nothing qualifies</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge <?= $box->sale_mode === 'enquire_now' ? 'rs-badge--enquire' : 'rs-badge--soft' ?>">
                                    <?= esc(['inherit' => 'Store setting', 'buy_now' => 'Buy', 'enquire_now' => 'Quoted'][$box->sale_mode] ?? $box->sale_mode) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rs-badge <?= $box->is_active ? 'rs-badge--soft' : 'rs-badge--out' ?>">
                                    <?= $box->is_active ? 'Live' : 'Hidden' ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <?php if ($canManage): ?>
                                    <a href="<?= site_url('admin/gift-boxes/' . $box->id . '/edit') ?>"
                                       class="rs-btn rs-btn--outline rs-btn--sm">Configure</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
