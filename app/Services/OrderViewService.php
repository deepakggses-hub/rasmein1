<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EnquiryModel;
use App\Models\OrderItemComponentModel;
use App\Models\OrderItemModel;
use App\Models\ShipmentModel;
use Config\Rasmein;

/**
 * Everything a CUSTOMER-FACING screen needs about one order or enquiry.
 *
 * WHY THIS IS A SERVICE AND NOT TWO CONTROLLERS
 *
 * Two screens show the same thing: the signed-in account page, and the public
 * tracking page a guest reaches with a reference and their email. Building
 * that payload twice guarantees they drift — one gets the quoted total and the
 * other keeps showing the indicative one, and nobody notices until a customer
 * is quoting two different numbers back at the shop.
 *
 * ACCESS CONTROL IS NOT HERE. This presents an order it is GIVEN. Deciding
 * whether the person may see it belongs to the caller: the account area scopes
 * by `customer_id`, and Track matches the reference against the email or phone
 * on the order. Putting it here would hide that decision inside a formatter.
 */
class OrderViewService
{
    /**
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    public function forCustomer(array $order): array
    {
        $config    = config(Rasmein::class);
        $orderId   = (int) $order['id'];
        $isEnquiry = ($order['journey_mode'] ?? '') === Rasmein::MODE_ENQUIRE;

        $items      = model(OrderItemModel::class)->forOrder($orderId);
        $itemIds    = array_map(static fn (array $i): int => (int) $i['id'], $items);
        $components = [];

        foreach (model(OrderItemComponentModel::class)->forItems($itemIds) as $component) {
            $components[(int) $component['order_item_id']][] = $component;
        }

        $enquiry = $isEnquiry
            ? model(EnquiryModel::class)->where('order_id', $orderId)->first()
            : null;

        return [
            'order'      => $order,
            'items'      => $items,
            'components' => $components,
            'shipment'   => model(ShipmentModel::class)->latestForOrder($orderId),
            'enquiry'    => $enquiry,
            'isEnquiry'  => $isEnquiry,
            'stage'      => $this->stage($enquiry, $config),
            'amount'     => $this->amount($order, $enquiry, $isEnquiry),
        ];
    }

    /**
     * The enquiry's stage, in words meant for the person who sent it.
     *
     * Null for an ordinary order, which has `status` and a shipment instead.
     *
     * @param array<string, mixed>|null $enquiry
     *
     * @return array{key: string, label: string, note: string}|null
     */
    private function stage(?array $enquiry, Rasmein $config): ?array
    {
        if ($enquiry === null) {
            return null;
        }

        $key = (string) ($enquiry['lead_status'] ?? 'new');

        // An unknown value reads as "Received" rather than blank: a stage added
        // to the database and not yet to the config must not leave the customer
        // looking at an empty box.
        if (! in_array($key, $config->enquiryStagesVisible, true)) {
            $key = 'new';
        }

        return [
            'key'   => $key,
            'label' => $config->enquiryStagesPublic[$key] ?? 'Received',
            'note'  => $config->enquiryStageNotes[$key] ?? '',
        ];
    }

    /**
     * WHICH NUMBER THE CUSTOMER SHOULD BE LOOKING AT.
     *
     * An enquiry's `grand_total` is what the basket added up to before anyone
     * looked at it — carriage, bulk pricing and anything bespoke are all still
     * unknown. Once an administrator sets `quoted_value`, THAT is the figure
     * the shop has actually offered, and it is the one that must appear.
     *
     * Showing the basket total beside an agreed quote is the worst outcome:
     * two numbers, neither labelled, and the customer reasonably believing the
     * smaller one. So when a quote exists the indicative total is not shown at
     * all — it is carried in the return for anyone who wants it, but the
     * screens do not draw it.
     *
     * `!== null`, never `?:` or `??` — a quote of exactly zero (a goodwill
     * replacement, a sample) is a real decision and must not fall back to the
     * basket total.
     *
     * @param array<string, mixed>      $order
     * @param array<string, mixed>|null $enquiry
     *
     * @return array{label: string, value: float, final: bool, indicative: float|null}
     */
    private function amount(array $order, ?array $enquiry, bool $isEnquiry): array
    {
        $basket = (float) $order['grand_total'];

        if (! $isEnquiry) {
            return ['label' => 'Total', 'value' => $basket, 'final' => true, 'indicative' => null];
        }

        $quoted = $enquiry['quoted_value'] ?? null;

        if ($quoted !== null && $quoted !== '') {
            return [
                'label'      => 'Your quote',
                'value'      => (float) $quoted,
                'final'      => true,
                'indicative' => $basket,
            ];
        }

        return [
            'label'      => 'Indicative total',
            'value'      => $basket,
            'final'      => false,
            'indicative' => null,
        ];
    }
}
