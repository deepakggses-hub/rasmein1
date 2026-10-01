<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\OrderModel;

/**
 * Track an order or enquiry without an account.
 *
 * Most orders here are placed as a guest, so "sign in to see your order" is a
 * door most customers do not have a key to. This asks for the reference AND
 * the email or phone that was given with it.
 *
 * WHY BOTH, AND WHY THAT PAIR
 *
 * A reference alone would be enough to read somebody's name, address and
 * totals — RSM-000123 is sequential enough to walk. Requiring a second factor
 * that only the person who placed it knows turns guessing a reference into
 * guessing a reference AND a contact, and the throttle below makes doing that
 * at any scale impractical.
 *
 * The match is on the order's OWN `customer_email` / `customer_phone`, not on
 * an account, because a guest has no account to match against.
 */
class Track extends StorefrontController
{
    /** Attempts allowed per IP per hour. */
    private const TRIES_PER_HOUR = 20;

    public function form()
    {
        return $this->page('storefront/track', [
            'crumbs' => [['label' => 'Track', 'url' => null]],
            'found'  => null,
        ], [
            'title'   => 'Track your order · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    public function lookup()
    {
        $ref     = strtoupper(trim((string) $this->request->getPost('reference')));
        $contact = trim((string) $this->request->getPost('contact'));

        if ($ref === '' || $contact === '') {
            return redirect()->back()->withInput()
                ->with('error', 'Enter your reference and the email or phone you gave us.');
        }

        /*
         * Throttled per IP.
         *
         * Twenty an hour is far more than anyone checking their own order
         * needs and far less than walking a range of references wants. On the
         * limit it says so plainly — unlike the not-found case below, being
         * rate limited is not information about anybody's order.
         */
        if (! service('throttler')->check(md5('track' . $this->request->getIPAddress()), self::TRIES_PER_HOUR, HOUR)) {
            return redirect()->back()->withInput()
                ->with('error', 'Too many attempts. Please wait a little while and try again.');
        }

        $order = model(OrderModel::class)->where('order_ref', $ref)->first();

        /*
         * ONE message for "no such reference" and "that is not the contact on
         * it". Distinguishing them would confirm a reference exists, which is
         * exactly what someone walking the range wants to learn — the same
         * reasoning as the generic sign-in failure.
         *
         * The comparison is deliberately forgiving about how a phone number
         * was typed, and case-insensitive on email, because a customer
         * re-entering their own details should not fail on a space.
         */
        if ($order === null || ! $this->contactMatches($order, $contact)) {
            return redirect()->back()->withInput()
                ->with('error', 'We could not find an order with those details. Check the reference and try again.');
        }

        /*
         * Remember it for this session, so the page can be refreshed and the
         * back button works — and so the detail URL carries the UUID rather
         * than the contact details, which would otherwise end up in browser
         * history, access logs and any Referer header.
         */
        $seen = (array) (session('tracked_orders') ?? []);
        $seen[] = $order['uuid'];
        session()->set('tracked_orders', array_values(array_unique($seen)));

        return redirect()->to(site_url('track/' . $order['uuid']));
    }

    /**
     * The result page.
     *
     * Reachable only for a UUID this session has already proved a contact for.
     * A UUID is not guessable, but that is a property of the generator rather
     * than of this code — the session check is what actually grants access,
     * the same rule the order confirmation page follows.
     */
    public function show(string $uuid)
    {
        if (! in_array($uuid, (array) (session('tracked_orders') ?? []), true)) {
            return redirect()->to(site_url('track'))
                ->with('error', 'Please look your order up again.');
        }

        $order = model(OrderModel::class)->where('uuid', $uuid)->first();

        if ($order === null) {
            return redirect()->to(site_url('track'))->with('error', 'That order is no longer available.');
        }

        // The same presenter the account page uses, so a guest and a signed-in
        // customer see the same stage and the same total.
        return $this->page('storefront/track', service('orderView')->forCustomer($order) + [
            'found'  => true,
            'crumbs' => [
                ['label' => 'Track', 'url' => site_url('track')],
                ['label' => $order['order_ref'], 'url' => null],
            ],
        ], [
            'title'   => $order['order_ref'] . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    /**
     * Is this the email or phone recorded on the order?
     *
     * @param array<string, mixed> $order
     */
    private function contactMatches(array $order, string $contact): bool
    {
        if (str_contains($contact, '@')) {
            // hash_equals, not ==: a timing comparison on an address is cheap
            // to avoid and the strings are already in hand.
            return hash_equals(
                strtolower((string) $order['customer_email']),
                strtolower($contact)
            );
        }

        $typed = preg_replace('/\D/', '', $contact) ?? '';
        $known = preg_replace('/\D/', '', (string) $order['customer_phone']) ?? '';

        // Last ten digits: people give their number with and without a country
        // code, and an exact match would reject their own.
        if (strlen($typed) < 10 || strlen($known) < 10) {
            return false;
        }

        return hash_equals(substr($known, -10), substr($typed, -10));
    }
}
