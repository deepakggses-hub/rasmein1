<?php
/**
 * One order or enquiry, as the CUSTOMER sees it.
 *
 * Shared by the signed-in account page and the public tracking page. Both are
 * given the same payload by OrderViewService, so neither can end up showing a
 * different total or a different stage from the other.
 *
 * Every key is passed EXPLICITLY by the caller. CodeIgniter merges a parent
 * view's data into a partial, which is how the related-products row once drew
 * the wrong product's photographs — naming them here means this renders the
 * same whatever page includes it.
 *
 * @var array<string, mixed>                   $order
 * @var array<int, array<string, mixed>>       $items
 * @var array<int, array<int, array>>          $components
 * @var array<string, mixed>|null              $shipment
 * @var array<string, mixed>|null              $enquiry
 * @var bool                                   $isEnquiry
 * @var array{key:string,label:string,note:string}|null $stage
 * @var array{label:string,value:float,final:bool,indicative:float|null} $amount
 */
$config = config(\Config\Rasmein::class);
?>

<?php /* ------------------------------------------------ enquiry progress --
         The stage the shop has put this at, in words meant for the person who
         sent it — never the internal "New / Won / Lost / Spam" vocabulary. */ ?>
<?php if ($isEnquiry && $stage !== null): ?>
    <section class="rs-stagecard">
        <h2 class="rs-eyebrow rs-eyebrow--plain">Where this has got to</h2>

        <?php
        // The stages worth drawing as a track. `lost` and `spam` are ends, not
        // steps, so a closed enquiry shows its state rather than a half-filled
        // progress bar implying it is still moving.
        $track  = ['new', 'contacted', 'quoted', 'won'];
        $closed = in_array($stage['key'], ['lost', 'spam'], true);
        $at     = array_search($stage['key'], $track, true);
        ?>

        <p class="rs-stagecard__now"><?= esc($stage['label']) ?></p>

        <?php if ($stage['note'] !== ''): ?>
            <p class="rs-stagecard__note"><?= esc($stage['note']) ?></p>
        <?php endif; ?>

        <?php if (! $closed): ?>
            <ol class="rs-steps" aria-label="Enquiry progress">
                <?php foreach ($track as $i => $key): ?>
                    <?php
                    $done = $at !== false && $i < $at;
                    $here = $at !== false && $i === $at;
                    ?>
                    <li class="rs-steps__item<?= $done ? ' is-done' : '' ?><?= $here ? ' is-here' : '' ?>"
                        <?= $here ? 'aria-current="step"' : '' ?>>
                        <span class="rs-steps__dot" aria-hidden="true"></span>
                        <span class="rs-steps__label"><?= esc($config->enquiryStagesPublic[$key]) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if (! empty($enquiry['needed_by'])): ?>
            <p class="rs-stagecard__meta num">
                Needed by <?= esc(date('j M Y', strtotime((string) $enquiry['needed_by']))) ?>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php /* --------------------------------------------------------- shipment */ ?>
<?php if ($shipment !== null && ! empty($shipment['tracking_number'])): ?>
    <section class="border border-brass bg-brass-soft/25 p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">On its way</h2>
        <p class="num mt-2 text-sm">
            <?= esc($shipment['courier_name']) ?> &middot;
            <span class="font-semibold"><?= esc($shipment['tracking_number']) ?></span>
        </p>
        <?php if (! empty($shipment['tracking_url'])): ?>
            <a href="<?= esc($shipment['tracking_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"
               class="rs-btn rs-btn--outline rs-btn--sm mt-3">Track this parcel</a>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php /* ------------------------------------------------------------ items */ ?>
<section class="border border-shell-line bg-white">
    <h2 class="border-b border-shell-line px-5 py-3 rs-eyebrow rs-eyebrow--plain">
        <?= $isEnquiry ? 'What you asked about' : 'What you ordered' ?>
    </h2>
    <ul class="divide-y divide-shell-line">
        <?php foreach ($items as $item): ?>
            <li class="px-5 py-4">
                <div class="num flex flex-wrap justify-between gap-x-4 gap-y-1">
                    <span class="font-medium"><?= esc($item['name_snapshot']) ?></span>
                    <span class="text-ink-muted">
                        &times; <?= (int) $item['quantity'] ?>
                        <span class="ml-3 font-semibold text-ink"><?= rs_money($item['line_total']) ?></span>
                    </span>
                </div>
                <?php if (! empty($item['variant_label'])): ?>
                    <p class="mt-1 text-sm text-ink-muted"><?= esc($item['variant_label']) ?></p>
                <?php endif; ?>
                <?php if (! empty($components[(int) $item['id']])): ?>
                    <ul class="mt-2 border-l-2 border-brass/40 pl-3 text-sm text-ink-muted">
                        <?php foreach ($components[(int) $item['id']] as $component): ?>
                            <li class="num flex justify-between gap-3">
                                <span><?= esc($component['name_snapshot']) ?> &times; <?= (int) $component['quantity'] ?></span>
                                <span><?= rs_money($component['line_total']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (! empty($item['gift_message'])): ?>
                    <p class="mt-2 border-l-2 border-shell-line pl-3 text-sm italic text-ink-muted">
                        &ldquo;<?= esc($item['gift_message']) ?>&rdquo;
                    </p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <dl class="num ml-auto max-w-xs space-y-1.5 border-t border-shell-line px-5 py-4 text-sm">
        <?php /* The breakdown is only meaningful while the basket total IS the
                 figure. Once a quote is agreed, showing subtotal + delivery
                 under a different grand total invites the customer to add them
                 up and find they do not reconcile. */ ?>
        <?php if (! $amount['final'] || ! $isEnquiry): ?>
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">Subtotal</dt><dd><?= rs_money($order['subtotal']) ?></dd></div>
            <?php if ((float) $order['discount_total'] > 0): ?>
                <div class="flex justify-between gap-4 text-pista-deep">
                    <dt>Discount</dt><dd>&minus;<?= rs_money($order['discount_total']) ?></dd>
                </div>
            <?php endif; ?>
            <?php if (! $isEnquiry): ?>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">Delivery</dt><dd><?= rs_money($order['shipping_total']) ?></dd></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="flex justify-between gap-4 border-t border-shell-line pt-1.5 font-semibold">
            <dt><?= esc($amount['label']) ?></dt>
            <dd class="<?= $amount['final'] && $isEnquiry ? 'text-mulberry' : '' ?>"><?= rs_money($amount['value']) ?></dd>
        </div>
    </dl>

    <?php if ($isEnquiry && ! $amount['final']): ?>
        <p class="border-t border-shell-line px-5 py-3 text-sm text-ink-muted">
            This is what the basket comes to today. Carriage, quantity pricing and
            anything bespoke are settled in your quote.
        </p>
    <?php endif; ?>
</section>

<?php /* ------------------------------------------------- what they told us */ ?>
<?php if ($isEnquiry && ! empty($enquiry['requirement_note'])): ?>
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain">What you told us</h2>
        <p class="mt-3 text-sm leading-relaxed text-ink-soft"><?= nl2br(esc($enquiry['requirement_note'])) ?></p>
    </section>
<?php endif; ?>

<?php /* ---------------------------------------------------------- address */ ?>
<?php if (! empty($order['ship_line1'])): ?>
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain"><?= $isEnquiry ? 'Address given' : 'Delivered to' ?></h2>
        <address class="mt-3 text-sm leading-relaxed not-italic text-ink-soft">
            <span class="font-semibold text-ink"><?= esc($order['ship_name']) ?></span><br>
            <?= esc($order['ship_line1']) ?><br>
            <?php if (! empty($order['ship_line2'])): ?><?= esc($order['ship_line2']) ?><br><?php endif; ?>
            <?= esc($order['ship_city']) ?>, <?= esc($order['ship_state']) ?>
            <span class="num"><?= esc($order['ship_postal_code']) ?></span>
        </address>
    </section>
<?php endif; ?>
