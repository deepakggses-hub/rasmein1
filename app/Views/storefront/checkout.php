<?= $this->extend('layouts/storefront') ?>

<?= $this->section('content') ?>
<?php
/**
 * Checkout. In Enquire mode the same page collects what a quote needs instead
 * of a delivery address — asking for an address before a price is agreed only
 * loses leads.
 *
 * @var array<string, mixed> $snapshot
 * @var bool   $isEnquiry
 * @var string $idempotencyKey
 * @var bool   $paymentLive
 */
$old = static fn (string $field, string $fallback = ''): string => (string) (old($field) ?? $fallback);
?>

<header class="border-b border-shell-line bg-shell-deep">
    <div class="rs-shell py-10">
        <?= view('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <p class="rs-eyebrow mt-6"><?= $isEnquiry ? 'Send enquiry' : 'Checkout' ?></p>
        <h1 class="mt-4 text-4xl sm:text-[2.75rem]">
            <?= $isEnquiry ? 'Tell us what you need' : 'Where is it going?' ?>
        </h1>
        <p class="mt-4 max-w-xl leading-relaxed text-ink-muted">
            <?= $isEnquiry
                ? 'We will come back with a written quote, usually within one working day. Nothing is charged now.'
                : 'A few details and the box is on its way. Packed by hand and dispatched within 48 hours.' ?>
        </p>
    </div>
</header>

<form method="post" action="<?= site_url('checkout') ?>" class="rs-shell py-10 lg:py-14">
    <?= csrf_field() ?>
    <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">

    <?php /* Honeypot. A person never sees this; a bot fills it in. */ ?>
    <div class="sr-only" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="lg:grid lg:grid-cols-[1fr_22rem] lg:gap-12 lg:items-start">
        <div class="space-y-10">

            <!-- ------------------------------------------------ contact -->
            <fieldset>
                <legend class="rs-eyebrow">Your details</legend>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="rs-label">Your name <span class="text-bad">*</span></span>
                        <input type="text" name="customer_name" class="rs-input" required maxlength="120"
                               autocomplete="name" value="<?= esc($old('customer_name'), 'attr') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Email <span class="text-bad">*</span></span>
                        <input type="email" name="customer_email" class="rs-input" required maxlength="191"
                               autocomplete="email" value="<?= esc($old('customer_email'), 'attr') ?>">
                        <span class="rs-help">Your <?= $isEnquiry ? 'quote' : 'order confirmation' ?> goes here.</span>
                    </label>
                    <label>
                        <span class="rs-label">Phone <span class="text-bad">*</span></span>
                        <input type="tel" name="customer_phone" class="rs-input" required maxlength="20"
                               autocomplete="tel" value="<?= esc($old('customer_phone'), 'attr') ?>">
                    </label>
                </div>
            </fieldset>

            <?php if ($isEnquiry): ?>
                <!-- -------------------------------------- quote details -->
                <fieldset>
                    <legend class="rs-eyebrow">About your requirement</legend>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label>
                            <span class="rs-label">Company <span class="text-ink-muted">(optional)</span></span>
                            <input type="text" name="company" class="rs-input" maxlength="120"
                                   autocomplete="organization" value="<?= esc($old('company'), 'attr') ?>">
                        </label>
                        <label>
                            <span class="rs-label">Best way to reach you</span>
                            <select name="preferred_contact" class="rs-select">
                                <?php foreach (['phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $old('preferred_contact', 'phone') === $value ? 'selected' : '' ?>>
                                        <?= esc($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span class="rs-label">How many boxes? <span class="text-ink-muted">(optional)</span></span>
                            <input type="number" name="expected_quantity" class="rs-input num" min="1" max="100000"
                                   inputmode="numeric" value="<?= esc($old('expected_quantity'), 'attr') ?>">
                        </label>
                        <label>
                            <span class="rs-label">Needed by <span class="text-ink-muted">(optional)</span></span>
                            <input type="date" name="needed_by" class="rs-input" value="<?= esc($old('needed_by'), 'attr') ?>">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="rs-label">Anything else we should know?</span>
                            <textarea name="requirement_note" class="rs-textarea" rows="4" maxlength="2000"
                                      placeholder="Branding on the sleeve, delivery to individual addresses, a budget per box…"><?= esc($old('requirement_note')) ?></textarea>
                        </label>
                    </div>
                </fieldset>
            <?php else: ?>
                <!-- ----------------------------------------- shipping -->
                <fieldset>
                    <legend class="rs-eyebrow">Delivery address</legend>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label>
                            <span class="rs-label">Recipient name <span class="text-bad">*</span></span>
                            <input type="text" name="ship_name" class="rs-input" required maxlength="120"
                                   value="<?= esc($old('ship_name'), 'attr') ?>">
                            <span class="rs-help">Who should the courier ask for?</span>
                        </label>
                        <label>
                            <span class="rs-label">Recipient phone <span class="text-bad">*</span></span>
                            <input type="tel" name="ship_phone" class="rs-input" required maxlength="20"
                                   value="<?= esc($old('ship_phone'), 'attr') ?>">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="rs-label">Address <span class="text-bad">*</span></span>
                            <input type="text" name="ship_line1" class="rs-input" required maxlength="191"
                                   autocomplete="address-line1" placeholder="Flat, house, building, street"
                                   value="<?= esc($old('ship_line1'), 'attr') ?>">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="rs-label">Area <span class="text-ink-muted">(optional)</span></span>
                            <input type="text" name="ship_line2" class="rs-input" maxlength="191"
                                   autocomplete="address-line2" value="<?= esc($old('ship_line2'), 'attr') ?>">
                        </label>
                        <label>
                            <span class="rs-label">Landmark <span class="text-ink-muted">(optional)</span></span>
                            <input type="text" name="ship_landmark" class="rs-input" maxlength="120"
                                   value="<?= esc($old('ship_landmark'), 'attr') ?>">
                        </label>
                        <label>
                            <span class="rs-label">PIN code <span class="text-bad">*</span></span>
                            <input type="text" name="ship_postal_code" class="rs-input num" required
                                   inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6"
                                   autocomplete="postal-code" value="<?= esc($old('ship_postal_code'), 'attr') ?>">
                            <span class="rs-help">Six digits.</span>
                        </label>
                        <label>
                            <span class="rs-label">City <span class="text-bad">*</span></span>
                            <input type="text" name="ship_city" class="rs-input" required maxlength="80"
                                   autocomplete="address-level2" value="<?= esc($old('ship_city'), 'attr') ?>">
                        </label>
                        <label>
                            <span class="rs-label">State <span class="text-bad">*</span></span>
                            <input type="text" name="ship_state" class="rs-input" required maxlength="80"
                                   autocomplete="address-level1" value="<?= esc($old('ship_state'), 'attr') ?>">
                        </label>
                    </div>

                    <label class="mt-5 flex cursor-pointer items-center gap-2.5 text-sm">
                        <input type="checkbox" name="bill_same_as_ship" value="1" class="accent-mulberry" checked>
                        <span>Billing address is the same</span>
                    </label>

                    <label class="mt-5 block max-w-sm">
                        <span class="rs-label">GSTIN <span class="text-ink-muted">(optional)</span></span>
                        <input type="text" name="bill_gstin" class="rs-input" maxlength="20"
                               value="<?= esc($old('bill_gstin'), 'attr') ?>">
                        <span class="rs-help">For a company invoice.</span>
                    </label>
                </fieldset>
            <?php endif; ?>

            <!-- ------------------------------------------------- extras -->
            <fieldset>
                <legend class="rs-eyebrow">Finishing touches</legend>

                <div class="mt-5 space-y-5">
                    <?php if ((bool) rs_setting('gift_message_enabled', true)): ?>
                        <label class="block">
                            <span class="rs-label">Gift message <span class="text-ink-muted">(optional)</span></span>
                            <textarea name="gift_message" class="rs-textarea" rows="3" maxlength="500"
                                      placeholder="Printed on a card and tucked inside."><?= esc($old('gift_message')) ?></textarea>
                            <span class="rs-help">Up to 500 characters, in your own words.</span>
                        </label>
                    <?php endif; ?>

                    <label class="block">
                        <span class="rs-label">Note for us <span class="text-ink-muted">(optional)</span></span>
                        <textarea name="customer_note" class="rs-textarea" rows="2" maxlength="1000"
                                  placeholder="Delivery timing, allergies, anything else."><?= esc($old('customer_note')) ?></textarea>
                    </label>
                </div>
            </fieldset>
        </div>

        <!-- -------------------------------------------------- summary -->
        <aside class="mt-10 lg:mt-0 lg:sticky lg:top-32">
            <div class="border border-shell-line bg-white p-6">
                <h2 class="rs-eyebrow rs-eyebrow--plain">
                    <?= (int) $snapshot['line_count'] ?> <?= $snapshot['line_count'] === 1 ? 'item' : 'items' ?>
                </h2>

                <ul class="mt-5 space-y-3 border-b border-shell-line pb-5 text-sm">
                    <?php foreach ($snapshot['lines'] as $line): ?>
                        <li class="num flex justify-between gap-4">
                            <span class="min-w-0">
                                <?= esc(rs_excerpt($line['name'], 34)) ?>
                                <?php if ($line['quantity'] > 1): ?>
                                    <span class="text-ink-muted">&times; <?= (int) $line['quantity'] ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="shrink-0"><?= rs_money($line['line_total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="num mt-5 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Subtotal</dt>
                        <dd><?= rs_money($snapshot['subtotal']) ?></dd>
                    </div>
                    <?php if ($snapshot['discount_total'] > 0): ?>
                        <div class="flex justify-between gap-4 text-pista-deep">
                            <dt>Discount</dt>
                            <dd>&minus;<?= rs_money($snapshot['discount_total']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Delivery</dt>
                        <dd><?= $snapshot['shipping_total'] > 0 ? rs_money($snapshot['shipping_total']) : 'Free' ?></dd>
                    </div>
                    <?php if ($snapshot['tax_total'] > 0): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Tax</dt>
                            <dd><?= rs_money($snapshot['tax_total']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <hr class="rs-rule my-5">

                <div class="num flex items-baseline justify-between gap-4">
                    <p class="font-semibold"><?= $isEnquiry ? 'Estimated' : 'To pay' ?></p>
                    <p class="font-display text-2xl font-semibold text-mulberry">
                        <?= rs_money($snapshot['grand_total']) ?>
                    </p>
                </div>

                <!-- What actually happens next. No gateway is live yet, so say so
                     rather than implying a card will be charged. -->
                <?php if ($isEnquiry): ?>
                    <p class="rs-help mt-3">
                        Indicative only. We will confirm the price in your quote before anything is due.
                    </p>
                <?php elseif (! $paymentLive): ?>
                    <p class="mt-3 border border-brass-soft bg-brass-soft/30 px-3 py-2.5 text-xs text-ink-soft">
                        Online payment is not live yet. Place the order and we will contact you
                        to arrange payment before dispatch.
                    </p>
                <?php endif; ?>

                <button type="submit" class="rs-btn rs-btn--primary mt-5 w-full">
                    <?= $isEnquiry ? 'Send enquiry' : 'Place order' ?>
                </button>

                <p class="rs-help mt-3 text-center">
                    <a href="<?= site_url('cart') ?>" class="rs-link">Back to <?= rs_cta_label(null, 'cart') ?></a>
                </p>
            </div>
        </aside>
    </div>
</form>

<?= $this->endSection() ?>
