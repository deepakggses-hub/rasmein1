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
                <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">What they asked for</h2>
                <p class="mt-2 text-sm leading-relaxed"><?= nl2br(esc($enquiry['requirement_note'])) ?></p>
            </section>
        <?php endif; ?>

        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">
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
            <dl class="num ml-auto max-w-xs space-y-1 border-t border-shell-line px-4 py-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Subtotal</dt><dd><?= rs_money($order['subtotal']) ?></dd></div>
                <?php if ((float) $order['discount_total'] > 0): ?>
                    <div class="flex justify-between gap-4 text-pista-deep">
                        <dt>Discount<?= ! empty($order['coupon_code']) ? ' (' . esc($order['coupon_code']) . ')' : '' ?></dt>
                        <dd>&minus;<?= rs_money($order['discount_total']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ((float) $order['shipping_total'] > 0): ?>
                    <div class="flex justify-between gap-4"><dt class="text-ink-muted">Delivery</dt><dd><?= rs_money($order['shipping_total']) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between gap-4 border-t border-shell-line pt-1 font-semibold">
                    <dt>Indicative</dt><dd><?= rs_money($order['grand_total']) ?></dd>
                </div>
                <?php /* What the customer is actually shown once it is set. The
                         two numbers side by side is the point: it is the only
                         place a staff member can see the gap. */ ?>
                <?php if ($enquiry['quoted_value'] !== null && $enquiry['quoted_value'] !== ''): ?>
                    <div class="flex justify-between gap-4 border-t border-shell-line pt-1 font-semibold text-mulberry">
                        <dt>Quoted</dt><dd><?= rs_money($enquiry['quoted_value']) ?></dd>
                    </div>
                    <p class="pt-1 text-right text-xs text-ink-muted">The customer sees this figure.</p>
                <?php endif; ?>
            </dl>
        </section>

        <?php /* ------------------------------------------- everything else --
                 These were all collected and none of them were drawn: the
                 address, the note at the bottom of the checkout form, the gift
                 message, and where the enquiry came from. A field captured and
                 never shown is worse than one never captured — someone filled
                 it in and nobody can read it. */ ?>
        <?php
        $hasAddress = ! empty($order['ship_line1']);
        $extras     = array_filter([
            'Note from the customer' => $order['customer_note'] ?? null,
            'Gift message'           => $order['gift_message'] ?? null,
            'Billing GSTIN'          => $order['bill_gstin'] ?? null,
        ], static fn ($v): bool => $v !== null && trim((string) $v) !== '');
        ?>

        <?php if ($hasAddress || $extras !== []): ?>
            <section class="grid gap-4 border border-shell-line bg-white p-4 sm:grid-cols-2">
                <?php if ($hasAddress): ?>
                    <div>
                        <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">Address given</h2>
                        <address class="mt-2 text-sm leading-relaxed not-italic">
                            <span class="font-medium"><?= esc($order['ship_name']) ?></span><br>
                            <?php if (! empty($order['ship_phone'])): ?>
                                <span class="num text-ink-muted"><?= esc($order['ship_phone']) ?></span><br>
                            <?php endif; ?>
                            <?= esc($order['ship_line1']) ?><br>
                            <?php if (! empty($order['ship_line2'])): ?><?= esc($order['ship_line2']) ?><br><?php endif; ?>
                            <?php if (! empty($order['ship_landmark'])): ?><?= esc($order['ship_landmark']) ?><br><?php endif; ?>
                            <?= esc($order['ship_city']) ?>, <?= esc($order['ship_state']) ?>
                            <span class="num"><?= esc($order['ship_postal_code']) ?></span>
                        </address>
                    </div>
                <?php endif; ?>

                <?php foreach ($extras as $label => $value): ?>
                    <div>
                        <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase"><?= esc($label) ?></h2>
                        <p class="mt-2 text-sm leading-relaxed"><?= nl2br(esc((string) $value)) ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- Follow-up log -->
        <section class="border border-shell-line bg-white">
            <h2 class="border-b border-shell-line px-4 py-3 font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">
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
                                <span class="num font-mono text-xs text-ink-muted">
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
                <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">Pipeline</h2>
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
            <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">Contact</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div><dt class="sr-only">Name</dt><dd class="font-medium"><?= esc($order['customer_name']) ?></dd></div>
                <?php if (! empty($enquiry['company'])): ?>
                    <div><dt class="sr-only">Company</dt><dd class="text-ink-muted"><?= esc($enquiry['company']) ?></dd></div>
                <?php endif; ?>
                <div><dd><a href="mailto:<?= esc($order['customer_email'], 'attr') ?>" class="rs-link"><?= esc($order['customer_email']) ?></a></dd></div>
                <div><dd class="num"><?= esc($order['customer_phone']) ?></dd></div>
                <div class="pt-2">
                    <dt class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">Prefers</dt>
                    <dd><?= esc($enquiry['preferred_contact']) ?></dd>
                </div>
                <?php if (! empty($enquiry['expected_quantity'])): ?>
                    <div class="pt-2">
                        <dt class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">Quantity wanted</dt>
                        <dd class="num font-semibold"><?= (int) $enquiry['expected_quantity'] ?> boxes</dd>
                    </div>
                <?php endif; ?>
                <?php if (! empty($enquiry['needed_by'])): ?>
                    <?php
                    // Flagged when it has already passed: a date that slid by
                    // unnoticed is the single most useful thing on this panel.
                    $due  = strtotime((string) $enquiry['needed_by']);
                    $past = date('Y-m-d', $due) < date('Y-m-d');
                    ?>
                    <div class="pt-2">
                        <dt class="font-mono text-xs tracking-[0.14em] text-ink-muted uppercase">Needed by</dt>
                        <dd class="num <?= $past ? 'font-semibold text-bad' : '' ?>">
                            <?= esc(date('j M Y', $due)) ?><?= $past ? ' — passed' : '' ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <?php /* The record itself. Nothing here is editable — it is the part of
                 an enquiry that simply is what it is, and it was previously
                 invisible: where the lead came from, what the spam check
                 thought, and when anything happened. */ ?>
        <section class="border border-shell-line bg-white p-4">
            <h2 class="font-mono text-xs tracking-[0.16em] text-ink-muted uppercase">Record</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <?php
                $stamp = static fn (?string $v): string => $v === null || $v === ''
                    ? '—'
                    : date('j M Y, H:i', strtotime($v));

                $rows = [
                    'Reference'  => $enquiry['enquiry_ref'],
                    'Order ref'  => $order['order_ref'],
                    'Source'     => $enquiry['source'] ?: 'checkout',
                    'Received'   => $stamp($order['placed_at'] ?? null),
                    'Updated'    => $stamp($enquiry['updated_at'] ?? null),
                    'Closed'     => $stamp($enquiry['closed_at'] ?? null),
                ];

                if ($enquiry['estimated_value'] !== null && $enquiry['estimated_value'] !== '') {
                    $rows['Estimated'] = rs_money($enquiry['estimated_value']);
                }

                // Only worth the line when it actually flagged something.
                if ((int) ($enquiry['spam_score'] ?? 0) > 0) {
                    $rows['Spam score'] = (int) $enquiry['spam_score'];
                }
                ?>
                <?php foreach ($rows as $label => $value): ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted"><?= esc($label) ?></dt>
                        <dd class="num text-right"><?= esc((string) $value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php /* The order row behind this enquiry — the address, the
                     payment state and the status history live there. */ ?>
            <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>"
               class="rs-btn rs-btn--outline rs-btn--sm mt-4 w-full">Open the order record</a>
        </section>
    </aside>
</div>

<?= $this->endSection() ?>
