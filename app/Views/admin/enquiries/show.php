<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php /** @var array<string, mixed> $enquiry, $order */ ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Enquiry',
    'heading'    => $enquiry['enquiry_ref'],
    'subheading' => $order['customer_name'] . ($enquiry['company'] ? ' · ' . $enquiry['company'] : '')
        . ' · received ' . date('j M Y', strtotime((string) $order['placed_at'])),
    'actions'    => '<a href="' . site_url('admin/enquiries') . '" class="rs-btn rs-btn--outline rs-btn--sm">All enquiries</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_20rem] lg:px-8">
    <div class="space-y-6">

        <?php if (! empty($enquiry['requirement_note'])): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">What they asked for</h2>
                <p class="mt-2 text-sm leading-relaxed"><?= nl2br(esc($enquiry['requirement_note'])) ?></p>
            </section>
        <?php endif; ?>

        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                Basket
            </h2>
            <ul class="divide-y divide-shell-line">
                <?php foreach ($items as $item): ?>
                    <li class="px-4 py-3">
                        <div class="num flex flex-wrap items-baseline justify-between gap-x-4 text-sm">
                            <span class="font-medium"><?= esc($item['name_snapshot']) ?></span>
                            <span class="text-ink-muted">
                                × <?= (int) $item['quantity'] ?>
                                <span class="ml-3 font-semibold text-ink"><?= rs_money($item['line_total']) ?></span>
                            </span>
                        </div>
                        <?php if (! empty($components[(int) $item['id']])): ?>
                            <ul class="mt-2 border-l-2 border-brass/40 pl-3 text-xs text-ink-muted">
                                <?php foreach ($components[(int) $item['id']] as $component): ?>
                                    <li class="num flex justify-between gap-3">
                                        <span><?= esc($component['name_snapshot']) ?> × <?= (int) $component['quantity'] ?></span>
                                        <span><?= rs_money($component['line_total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="num border-t border-shell-line px-4 py-3 text-right text-sm">
                <span class="text-ink-muted">Indicative</span>
                <span class="ml-2 font-semibold"><?= rs_money($order['grand_total']) ?></span>
            </p>
        </section>

        <!-- Follow-up log -->
        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">
                Follow-ups
            </h2>

            <?php if ($canManage): ?>
                <form method="post" action="<?= site_url('admin/enquiries/' . $enquiry['id'] . '/note') ?>"
                      class="border-b border-shell-line p-4">
                    <?= csrf_field() ?>
                    <div class="flex flex-wrap gap-3">
                        <label class="w-32">
                            <span class="rs-label">Kind</span>
                            <select name="note_type" class="rs-select">
                                <?php foreach (['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'quote' => 'Quote'] as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= esc($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="min-w-52 flex-1">
                            <span class="rs-label">What happened</span>
                            <input type="text" name="note" class="rs-input" maxlength="2000" required
                                   placeholder="Called, asked for samples of the tea box">
                        </label>
                    </div>
                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm mt-3">Add</button>
                </form>
            <?php endif; ?>

            <?php if ($notes === []): ?>
                <p class="px-4 py-6 text-sm text-ink-muted">Nothing logged yet.</p>
            <?php else: ?>
                <ul class="divide-y divide-shell-line text-sm">
                    <?php foreach ($notes as $note): ?>
                        <li class="px-4 py-3">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <span>
                                    <span class="rs-badge rs-badge--soft"><?= esc($note['note_type']) ?></span>
                                    <span class="ml-2"><?= esc($note['note']) ?></span>
                                </span>
                                <span class="num font-mono text-[0.625rem] text-ink-muted">
                                    <?= esc($note['author'] ?? 'System') ?> ·
                                    <?= esc(date('j M, H:i', strtotime((string) $note['created_at']))) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <aside class="space-y-5">
        <?php if ($canManage): ?>
            <section class="border border-shell-line bg-white p-4">
                <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Pipeline</h2>
                <form method="post" action="<?= site_url('admin/enquiries/' . $enquiry['id']) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="rs-label">Stage</span>
                        <select name="lead_status" class="rs-select">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= esc($key, 'attr') ?>" <?= $enquiry['lead_status'] === $key ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">Owner</span>
                        <select name="assigned_to_admin_id" class="rs-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($staff as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"
                                    <?= (int) ($enquiry['assigned_to_admin_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                                    <?= esc($person['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">Quoted value</span>
                        <input type="number" name="quoted_value" class="rs-input num" step="0.01" min="0"
                               value="<?= esc($enquiry['quoted_value'] ?? '', 'attr') ?>">
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">Follow up on</span>
                        <input type="date" name="followup_at" class="rs-input"
                               value="<?= esc($enquiry['followup_at'] !== null ? date('Y-m-d', strtotime((string) $enquiry['followup_at'])) : '', 'attr') ?>">
                    </label>
                    <label class="mt-3 block">
                        <span class="rs-label">If lost, why?</span>
                        <input type="text" name="lost_reason" class="rs-input" maxlength="255"
                               value="<?= esc($enquiry['lost_reason'] ?? '', 'attr') ?>">
                    </label>
                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm mt-4 w-full">Save</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="border border-shell-line bg-white p-4">
            <h2 class="font-mono text-[0.625rem] tracking-[0.16em] text-ink-muted uppercase">Contact</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div><dt class="sr-only">Name</dt><dd class="font-medium"><?= esc($order['customer_name']) ?></dd></div>
                <?php if (! empty($enquiry['company'])): ?>
                    <div><dt class="sr-only">Company</dt><dd class="text-ink-muted"><?= esc($enquiry['company']) ?></dd></div>
                <?php endif; ?>
                <div><dd><a href="mailto:<?= esc($order['customer_email'], 'attr') ?>" class="rs-link"><?= esc($order['customer_email']) ?></a></dd></div>
                <div><dd class="num"><?= esc($order['customer_phone']) ?></dd></div>
                <div class="pt-2">
                    <dt class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">Prefers</dt>
                    <dd><?= esc($enquiry['preferred_contact']) ?></dd>
                </div>
                <?php if (! empty($enquiry['expected_quantity'])): ?>
                    <div class="pt-2">
                        <dt class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">Quantity wanted</dt>
                        <dd class="num font-semibold"><?= (int) $enquiry['expected_quantity'] ?> boxes</dd>
                    </div>
                <?php endif; ?>
                <?php if (! empty($enquiry['needed_by'])): ?>
                    <div class="pt-2">
                        <dt class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">Needed by</dt>
                        <dd class="num"><?= esc(date('j M Y', strtotime((string) $enquiry['needed_by']))) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>
    </aside>
</div>

<?= $this->endSection() ?>
