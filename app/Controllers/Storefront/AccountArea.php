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

        $orders = model(OrderModel::class)
            ->select('id, uuid, order_ref, journey_mode, status, payment_status, grand_total, placed_at')
            ->where('customer_id', $id)
            ->orderBy('id', 'DESC')
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
        $model = model(OrderModel::class);
        $rows  = $model->where('customer_id', $this->customerId())
            ->orderBy('id', 'DESC')
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

        $items   = model(OrderItemModel::class)->forOrder((int) $order['id']);
        $itemIds = array_map(static fn (array $i): int => (int) $i['id'], $items);
        $grouped = [];

        foreach (model(\App\Models\OrderItemComponentModel::class)->forItems($itemIds) as $component) {
            $grouped[(int) $component['order_item_id']][] = $component;
        }

        return $this->page('storefront/account/order', [
            'order'      => $order,
            'items'      => $items,
            'components' => $grouped,
            'shipment'   => model(\App\Models\ShipmentModel::class)->latestForOrder((int) $order['id']),
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

    public function wishlist(): string
    {
        return $this->page('storefront/account/wishlist', [
            'items'  => model(WishlistModel::class)->forCustomer($this->customerId()),
            'crumbs' => [
                ['label' => 'Your account', 'url' => site_url('account')],
                ['label' => 'Wishlist', 'url' => null],
            ],
        ], ['title' => 'Your wishlist · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function toggleWishlist()
    {
        $productId = (int) $this->request->getPost('product_id');
        $product   = $productId > 0 ? model(ProductModel::class)->find($productId) : null;

        if ($product === null) {
            return redirect()->back()->with('error', 'That product is not available.');
        }

        $added = model(WishlistModel::class)->toggle($this->customerId(), $productId);

        $target = (string) ($this->request->getPost('return_to') ?? '');
        $safe   = $target !== '' && ! preg_match('#^[a-z]+://#i', $target) && ! str_starts_with($target, '//')
            ? site_url(ltrim($target, '/'))
            : site_url('wishlist');

        return redirect()->to($safe)->with(
            'success',
            $added ? $product->name . ' saved to your wishlist.' : $product->name . ' removed from your wishlist.'
        );
    }

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

    public function changePassword()
    {
        $rules = [
            'current_password' => ['label' => 'Current password', 'rules' => 'required'],
            'new_password'     => ['label' => 'New password', 'rules' => 'required|min_length[10]|max_length[200]'],
            'confirm_password' => ['label' => 'Confirmation', 'rules' => 'required|matches[new_password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('account'))->with('errors', $this->validator->getErrors());
        }

        $customers = model(CustomerModel::class);
        $customer  = $customers->find($this->customerId());

        if ($customer === null || $customer['password_hash'] === null
            || ! password_verify((string) $this->request->getPost('current_password'), $customer['password_hash'])) {
            return redirect()->to(site_url('account'))->with('error', 'Your current password is not correct.');
        }

        $customers->update($customer['id'], [
            'password_hash' => $customers->hashPassword((string) $this->request->getPost('new_password')),
        ]);

        return redirect()->to(site_url('account'))->with('success', 'Password updated.');
    }
}
