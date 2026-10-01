<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\WishlistModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The signed-in customer's own area. Everything here is behind the
 * customerAuth filter.
 *
 * Every query is scoped to session('customer_id'). Not one of them takes an
 * owner from the URL — that is the whole defence against reading someone
 * else's orders or addresses by changing a number.
 */
class AccountArea extends StorefrontController
{
    private function customerId(): int
    {
        return (int) session('customer_id');
    }

    public function dashboard(): string
    {
        $id = $this->customerId();

        /*
         * The enquiry's pipeline stage travels with the row.
         *
         * An enquiry IS an order row, and its `status` sits at "pending" for
         * the whole conversation — so the list would show "Pending" beside a
         * quote that was agreed a week ago. A LEFT JOIN, because most rows are
         * ordinary purchases with no enquiry to join to.
         */
        $orders = model(OrderModel::class)
            ->select('orders.id, orders.uuid, orders.order_ref, orders.journey_mode, orders.status,
                      orders.payment_status, orders.grand_total, orders.placed_at,
                      e.lead_status, e.quoted_value')
            ->join('enquiries e', 'e.order_id = orders.id AND e.deleted_at IS NULL', 'left')
            ->where('orders.customer_id', $id)
            ->orderBy('orders.id', 'DESC')
            ->findAll(5);

        $spend = (float) (db_connect()->table('orders')
            ->selectSum('grand_total', 'total')
            ->where('customer_id', $id)
            ->where('journey_mode', 'buy_now')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->get()->getRowArray()['total'] ?? 0);

        return $this->page('storefront/account/dashboard', [
            'customer'  => model(CustomerModel::class)->find($id),
            'orders'    => $orders,
            'spend'     => $spend,
            'addresses' => count(model(CustomerAddressModel::class)->forCustomer($id)),
            'wishlist'  => count(model(WishlistModel::class)->productIds($id)),
            'crumbs'    => [['label' => 'Your account', 'url' => null]],
        ], ['title' => 'Your account · ' . $this->brand->brandName, 'noindex' => true]);
    }

    // ------------------------------------------------------------ orders

    public function orders(): string
    {
        // Same join as the dashboard: an enquiry's stage, not its order status.
        $model = model(OrderModel::class);
        $rows  = $model
            ->select('orders.*, e.lead_status, e.quoted_value')
            ->join('enquiries e', 'e.order_id = orders.id AND e.deleted_at IS NULL', 'left')
            ->where('orders.customer_id', $this->customerId())
            ->orderBy('orders.id', 'DESC')
            ->paginate(10);

        return $this->page('storefront/account/orders', [
            'orders' => $rows,
            'pager'  => $model->pager,
            'crumbs' => [
                ['label' => 'Your account', 'url' => site_url('account')],
                ['label' => 'Orders', 'url' => null],
            ],
        ], ['title' => 'Your orders · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function order(string $uuid): string
    {
        // Scoped by customer as well as uuid — ownership, not obscurity.
        $order = model(OrderModel::class)
            ->where('uuid', $uuid)
            ->where('customer_id', $this->customerId())
            ->first();

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        /*
         * One presenter, shared with the public tracking page, so both screens
         * agree on the stage wording and on WHICH total to show — the quote
         * once an administrator has set one, the indicative basket until then.
         */
        $view = service('orderView')->forCustomer($order);

        return $this->page('storefront/account/order', $view + [
            'crumbs'     => [
                ['label' => 'Your account', 'url' => site_url('account')],
                ['label' => 'Orders', 'url' => site_url('account/orders')],
                ['label' => $order['order_ref'], 'url' => null],
            ],
        ], ['title' => $order['order_ref'] . ' · ' . $this->brand->brandName, 'noindex' => true]);
    }

    // --------------------------------------------------------- addresses

    public function addresses(): string
    {
        $editing = null;
        $editId  = (int) $this->request->getGet('edit');

        if ($editId > 0) {
            $editing = model(CustomerAddressModel::class)->findForCustomer($editId, $this->customerId());
        }

        return $this->page('storefront/account/addresses', [
            'addresses' => model(CustomerAddressModel::class)->forCustomer($this->customerId()),
            'editing'   => $editing,
            'crumbs'    => [
                ['label' => 'Your account', 'url' => site_url('account')],
                ['label' => 'Addresses', 'url' => null],
            ],
        ], ['title' => 'Your addresses · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function saveAddress()
    {
        $model = model(CustomerAddressModel::class);
        $id    = (int) $this->request->getPost('id');

        // An id from the form is only honoured if it already belongs to you.
        if ($id > 0 && $model->findForCustomer($id, $this->customerId()) === null) {
            return redirect()->to(site_url('account/addresses'))
                ->with('error', 'That address is not on your account.');
        }

        $payload = [
            'customer_id'    => $this->customerId(),
            'label'          => trim((string) $this->request->getPost('label')) ?: null,
            'recipient_name' => trim((string) $this->request->getPost('recipient_name')),
            'phone'          => trim((string) $this->request->getPost('phone')),
            'line1'          => trim((string) $this->request->getPost('line1')),
            'line2'          => trim((string) $this->request->getPost('line2')) ?: null,
            'landmark'       => trim((string) $this->request->getPost('landmark')) ?: null,
            'city'           => trim((string) $this->request->getPost('city')),
            'state'          => trim((string) $this->request->getPost('state')),
            'postal_code'    => trim((string) $this->request->getPost('postal_code')),
            'country'        => 'India',
        ];

        if ($id > 0) {
            $payload['id'] = $id;
        }

        $saved = $id > 0 ? $model->update($id, $payload) : $model->insert($payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id > 0 ? $id : (int) $model->getInsertID();

        // The first address a customer saves becomes their default.
        if (count($model->forCustomer($this->customerId())) === 1) {
            $model->makeDefault($this->customerId(), $newId);
        } elseif ($this->request->getPost('is_default_shipping') !== null) {
            $model->makeDefault($this->customerId(), $newId);
        }

        return redirect()->to(site_url('account/addresses'))->with('success', 'Address saved.');
    }

    public function deleteAddress()
    {
        $model = model(CustomerAddressModel::class);
        $id    = (int) $this->request->getPost('id');

        if ($model->findForCustomer($id, $this->customerId()) === null) {
            return redirect()->to(site_url('account/addresses'))
                ->with('error', 'That address is not on your account.');
        }

        $model->delete($id);

        return redirect()->to(site_url('account/addresses'))->with('success', 'Address removed.');
    }

    public function makeDefaultAddress()
    {
        $model = model(CustomerAddressModel::class);
        $id    = (int) $this->request->getPost('id');

        if ($model->findForCustomer($id, $this->customerId()) === null) {
            return redirect()->to(site_url('account/addresses'))
                ->with('error', 'That address is not on your account.');
        }

        $model->makeDefault($this->customerId(), $id);

        return redirect()->to(site_url('account/addresses'))->with('success', 'Default address set.');
    }

    // ---------------------------------------------------------- wishlist

    /*
     * The wishlist moved to Storefront\Wishlist.
     *
     * It is public now — a guest can save things before they have an account —
     * so it cannot live in a controller behind the customerAuth filter. The two
     * actions that used to sit here were unreachable after the routes changed,
     * and a dead action is worse than a missing one: the next person reads it,
     * assumes it runs, and edits the wrong file.
     */

    // ----------------------------------------------------------- details

    public function saveDetails()
    {
        $customers = model(CustomerModel::class);
        $id        = $this->customerId();

        $payload = [
            'id'               => $id,
            'name'             => trim((string) $this->request->getPost('name')),
            'phone'            => trim((string) $this->request->getPost('phone')) ?: null,
            'marketing_opt_in' => $this->request->getPost('marketing_opt_in') !== null ? 1 : 0,
        ];

        // Email is deliberately not editable here: changing it would need
        // verification of the new address, which belongs in its own flow.
        if ($customers->update($id, $payload) === false) {
            return redirect()->back()->withInput()->with('errors', $customers->errors());
        }

        session()->set('customer_name', $payload['name']);

        return redirect()->to(site_url('account'))->with('success', 'Details updated.');
    }

    /*
     * changePassword() USED TO LIVE HERE AND IS DELETED.
     *
     * It had no route, so nothing could reach it — and the account page
     * carried a form posting to `account/password`, which 404'd. It asked for
     * a "current password" that a passwordless account has never had.
     *
     * Deleted rather than routed, for the same reason the password sign-in
     * actions were: an unreachable password path sitting beside a passwordless
     * one invites someone to wire it back up, and that reopens exactly what
     * one-time codes closed. See "Sign-in: one-time codes, and Google".
     */
}
