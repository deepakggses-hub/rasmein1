<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\OrderItemComponentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * Checkout. In Enquire mode the same form becomes an enquiry form: the fields
 * for a quote replace the ones for a payment, and the submission is captured as
 * a lead rather than an order to be paid.
 */
class Checkout extends StorefrontController
{
    private const KEY_SESSION = 'checkout_idempotency_key';

    public function show()
    {
        $snapshot = service('cart')->snapshot();

        if ($snapshot['is_empty']) {
            return redirect()->to(site_url('cart'))
                ->with('error', 'There is nothing to check out yet.');
        }

        if ($snapshot['blocking'] !== []) {
            return redirect()->to(site_url('cart'))
                ->with('error', $snapshot['blocking'][0]['message']);
        }

        // One key per visit to this page. Carried on the form so a double-click
        // or a refresh-resubmit returns the same order instead of a second one.
        $key = session(self::KEY_SESSION);

        if ($key === null) {
            $key = bin2hex(random_bytes(24));
            session()->set(self::KEY_SESSION, $key);
        }

        $isEnquiry = $snapshot['journey_mode'] === Rasmein::MODE_ENQUIRE;

        return $this->page('storefront/checkout', [
            'snapshot'       => $snapshot,
            'isEnquiry'      => $isEnquiry,
            'idempotencyKey' => $key,
            'prefill'        => $this->prefill(),
            'paymentLive'    => (bool) $this->settings->get('payment_enabled', false),
            'crumbs'         => [
                ['label' => rs_cta_label(null, 'cart'), 'url' => site_url('cart')],
                ['label' => $isEnquiry ? 'Send enquiry' : 'Checkout', 'url' => null],
            ],
        ], [
            'title'   => ($isEnquiry ? 'Send your enquiry' : 'Checkout') . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    public function place()
    {
        $snapshot  = service('cart')->snapshot();
        $isEnquiry = $snapshot['journey_mode'] === Rasmein::MODE_ENQUIRE;

        if ($snapshot['is_empty']) {
            return redirect()->to(site_url('cart'))->with('error', 'Your cart is empty.');
        }

        // Honeypot: a real browser leaves this empty. Bots fill everything.
        // Enquiry forms in particular trigger staff notifications, so they are
        // worth protecting (CLAUDE.md §8).
        $spamScore = trim((string) $this->request->getPost('website')) !== '' ? 100 : 0;

        if ($spamScore >= 100) {
            log_message('warning', 'Checkout honeypot triggered from {ip}', [
                'ip' => $this->request->getIPAddress(),
            ]);

            // Behave normally rather than announcing the trap.
            return redirect()->to(site_url('cart'))
                ->with('error', 'We could not process that. Please try again.');
        }

        if (! $this->validate($this->rules($isEnquiry))) {
            return redirect()->back()->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $input = $this->request->getPost(array_keys($this->rules($isEnquiry)));
        $input['bill_same_as_ship'] = $this->request->getPost('bill_same_as_ship') !== null;
        $input['spam_score']        = $spamScore;

        foreach (['bill_name', 'bill_line1', 'bill_line2', 'bill_city', 'bill_state',
                  'bill_postal_code', 'bill_gstin', 'company', 'preferred_contact',
                  'requirement_note', 'expected_quantity', 'needed_by',
                  'ship_landmark', 'ship_line2'] as $optional) {
            $input[$optional] = $this->request->getPost($optional);
        }

        /*
         * The key the FORM was rendered with, then the session, then a new one.
         *
         * The posted field was inert: the session key is removed after a
         * successful order, so pressing Back and resubmitting produced a fresh
         * random key, the duplicate check passed, and the same basket became a
         * second order. The whole point of a hidden idempotency field is that it
         * survives that.
         *
         * Format-checked rather than trusted — it goes into a unique index, and
         * an arbitrary string from a form should not decide what a row looks
         * like.
         */
        $posted = (string) $this->request->getPost('idempotency_key');

        $key = preg_match('/^[a-f0-9]{32,64}$/', $posted) === 1
            ? $posted
            : (session(self::KEY_SESSION) ?? bin2hex(random_bytes(24)));

        $result = service('orders')->placeFromCart($input, $key);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        session()->remove(self::KEY_SESSION);

        // Remember what this visitor may view, so the confirmation page is not
        // readable by anyone who guesses a UUID (CLAUDE.md §6).
        $seen = session('viewable_orders') ?? [];
        $seen[] = $result['order']['uuid'];
        session()->set('viewable_orders', array_slice(array_unique($seen), -20));

        return redirect()->to(site_url('order/' . $result['order']['uuid']));
    }

    public function confirmation(string $uuid): string
    {
        $orders = model(OrderModel::class);
        $order  = $orders->findByUuid($uuid);

        if ($order === null || ! $this->mayView($order)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $items      = model(OrderItemModel::class)->forOrder((int) $order['id']);
        $itemIds    = array_map(static fn (array $i): int => (int) $i['id'], $items);
        $components = model(OrderItemComponentModel::class)->forItems($itemIds);

        $grouped = [];

        foreach ($components as $component) {
            $grouped[(int) $component['order_item_id']][] = $component;
        }

        $isEnquiry = $order['journey_mode'] === Rasmein::MODE_ENQUIRE;

        return $this->page('storefront/order_confirmation', [
            'order'      => $order,
            'items'      => $items,
            'components' => $grouped,
            'isEnquiry'  => $isEnquiry,
            'enquiry'    => $isEnquiry
                ? model(\App\Models\EnquiryModel::class)->findByOrderId((int) $order['id'])
                : null,
            'crumbs'     => [['label' => $isEnquiry ? 'Enquiry sent' : 'Order placed', 'url' => null]],
        ], [
            'title'   => ($isEnquiry ? 'Enquiry ' : 'Order ') . $order['order_ref']
                . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    /**
     * An unguessable UUID is not on its own an access control. The visitor must
     * either have placed this order in this session, or own it as a signed-in
     * customer.
     *
     * @param array<string, mixed> $order
     */
    private function mayView(array $order): bool
    {
        $customerId = session('customer_id');

        if ($customerId !== null && (int) $order['customer_id'] === (int) $customerId) {
            return true;
        }

        return in_array($order['uuid'], session('viewable_orders') ?? [], true);
    }

    /**
     * What a signed-in customer should not have to type again.
     *
     * Read from the account and the default shipping address, both of which
     * are already scoped by `session('customer_id')` — nothing here trusts an
     * id from the request, so a signed-out visitor simply gets an empty array
     * and the form behaves exactly as it always did.
     *
     * These are DEFAULTS, not locks. The view prefers `old()` over them, so a
     * validation failure returns what the person actually typed rather than
     * quietly reinstating their profile; and every field stays editable,
     * because the address a gift goes to is very often not the buyer's own.
     *
     * Nothing is copied into the account in the other direction — an order
     * placed to a friend's address must not silently become the customer's
     * default. That is what Account → Addresses is for.
     *
     * @return array<string, string>
     */
    private function prefill(): array
    {
        $customerId = session('customer_id');

        if ($customerId === null) {
            return [];
        }

        $customer = model(\App\Models\CustomerModel::class)->find((int) $customerId);

        if ($customer === null) {
            return [];
        }

        $out = [
            'customer_name'  => (string) ($customer['name'] ?? ''),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => (string) ($customer['phone'] ?? ''),
        ];

        $addresses = model(\App\Models\CustomerAddressModel::class)->forCustomer((int) $customerId);

        if ($addresses === []) {
            return $out;
        }

        // The one marked default, else the first — forCustomer() already sorts
        // defaults first, so this is a fallback rather than a second opinion.
        $ship = null;

        foreach ($addresses as $address) {
            if ((int) ($address['is_default_shipping'] ?? 0) === 1) { $ship = $address; break; }
        }

        $ship ??= $addresses[0];

        $out += [
            'ship_name'        => (string) ($ship['recipient_name'] ?? $customer['name'] ?? ''),
            'ship_phone'       => (string) ($ship['phone'] ?? $customer['phone'] ?? ''),
            'ship_line1'       => (string) ($ship['line1'] ?? ''),
            'ship_line2'       => (string) ($ship['line2'] ?? ''),
            'ship_landmark'    => (string) ($ship['landmark'] ?? ''),
            'ship_city'        => (string) ($ship['city'] ?? ''),
            'ship_state'       => (string) ($ship['state'] ?? ''),
            'ship_postal_code' => (string) ($ship['postal_code'] ?? ''),
            'ship_country'     => (string) ($ship['country'] ?? ''),
        ];

        return $out;
    }

    /** @return array<string, array<string, string>> */
    private function rules(bool $isEnquiry): array
    {
        $rules = [
            'customer_name' => [
                'label'  => 'Your name',
                'rules'  => 'required|min_length[2]|max_length[120]',
            ],
            'customer_email' => [
                'label' => 'Email',
                'rules' => 'required|valid_email|max_length[191]',
            ],
            'customer_phone' => [
                'label' => 'Phone',
                'rules' => 'required|min_length[10]|max_length[20]',
            ],
            'gift_message' => [
                'label' => 'Gift message',
                'rules' => 'permit_empty|max_length[500]',
            ],
            'customer_note' => [
                'label' => 'Note',
                'rules' => 'permit_empty|max_length[1000]',
            ],
        ];

        if ($isEnquiry) {
            $rules += [
                'company'           => ['label' => 'Company', 'rules' => 'permit_empty|max_length[120]'],
                'preferred_contact' => ['label' => 'Best way to reach you', 'rules' => 'permit_empty|in_list[email,phone,whatsapp]'],
                'expected_quantity' => ['label' => 'How many boxes', 'rules' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100000]'],
                'requirement_note'  => ['label' => 'Your requirement', 'rules' => 'permit_empty|max_length[2000]'],
                /*
                 * `needed_by` must be today or later.
                 *
                 * The input carries min/max so the picker behaves, but that is
                 * a hint to a browser — this is the check. A date in the past
                 * is either a typo or a bot, and either way it would sit in
                 * the pipeline reading as already overdue.
                 *
                 * `valid_date` first, or a string like "soon" reaches the
                 * comparison and strtotime() turns it into today.
                 */
                'needed_by' => [
                    'label' => 'Needed by',
                    'rules' => 'permit_empty|valid_date[Y-m-d]|rs_not_past',
                    /*
                     * No message for rs_not_past here: the rule sets $error by
                     * reference and CI4 prefers that, so anything configured
                     * would never be seen. A message that cannot fire is worse
                     * than none — the next person changes it and nothing
                     * happens.
                     */
                    'errors' => ['valid_date' => 'That does not look like a date.'],
                ],
            ];
        }

        // An enquiry does not need a delivery address yet — that is settled when
        // the quote is agreed. Asking for it up front loses leads.
        if (! $isEnquiry) {
            $rules += [
                'ship_name'        => ['label' => 'Recipient name', 'rules' => 'required|min_length[2]|max_length[120]'],
                'ship_phone'       => ['label' => 'Recipient phone', 'rules' => 'required|min_length[10]|max_length[20]'],
                'ship_line1'       => ['label' => 'Address', 'rules' => 'required|max_length[191]'],
                'ship_city'        => ['label' => 'City', 'rules' => 'permit_empty|max_length[80]'],
                'ship_state'       => ['label' => 'State', 'rules' => 'required|max_length[80]'],
                'ship_postal_code' => ['label' => 'PIN code', 'rules' => 'required|regex_match[/^[1-9][0-9]{5}$/]'],
                'ship_country'     => ['label' => 'Country', 'rules' => 'permit_empty|max_length[60]'],
            ];
        }

        return $rules;
    }
}
