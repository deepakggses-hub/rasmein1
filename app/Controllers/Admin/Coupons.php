<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CouponModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * Discount codes.
 *
 * The admin sets the terms; PricingService decides the money. Nothing here
 * writes a discount amount anywhere — the value is always recomputed at
 * checkout from these columns, so an expired or exhausted code cannot ride
 * along on an old cart.
 */
class Coupons extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        $model = model(CouponModel::class);
        $rows  = $model->orderBy('is_active', 'DESC')->orderBy('id', 'DESC')
            ->paginate(config(Rasmein::class)->adminPerPage);

        return $this->adminPage('admin/coupons/index', [
            'coupons' => $rows,
            'pager'   => $model->pager,
            'total'   => $model->pager->getTotal(),
        ], 'Coupons');
    }

    public function create()
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/coupons/form', ['coupon' => null, 'redemptions' => 0], 'New coupon');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        $coupon = model(CouponModel::class)->find($id);

        if ($coupon === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/coupons/form', [
            'coupon'      => $coupon,
            'redemptions' => db_connect()->table('coupon_redemptions')->where('coupon_id', $id)->countAllResults(),
        ], 'Edit ' . $coupon['code']);
    }

    public function store()
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        if (model(CouponModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    /**
     * Soft delete. A redeemed coupon is referenced by past orders, so the row
     * has to stay readable for reporting and invoices.
     */
    public function delete(int $id)
    {
        if ($denied = $this->deny('coupons.manage')) {
            return $denied;
        }

        $model  = model(CouponModel::class);
        $coupon = $model->find($id);

        if ($coupon === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        service('audit')->log('deleted', 'coupons', 'coupon', $id, $coupon['code']);

        return redirect()->to(site_url('admin/coupons'))
            ->with('success', $coupon['code'] . ' removed. Orders that used it keep their record.');
    }

    private function save(?int $id)
    {
        $model = model(CouponModel::class);
        $type  = (string) $this->request->getPost('discount_type');

        $payload = [
            'code'                     => strtoupper(trim((string) $this->request->getPost('code'))),
            'description'              => trim((string) $this->request->getPost('description')) ?: null,
            'discount_type'            => $type,
            // Free shipping carries no value of its own.
            'value'                    => $type === 'free_shipping' ? 0 : (float) $this->request->getPost('value'),
            'min_order_value'          => (float) $this->request->getPost('min_order_value'),
            'max_discount'             => $this->request->getPost('max_discount') !== ''
                ? (float) $this->request->getPost('max_discount') : null,
            'usage_limit_total'        => $this->request->getPost('usage_limit_total') !== ''
                ? max(1, (int) $this->request->getPost('usage_limit_total')) : null,
            'usage_limit_per_customer' => $this->request->getPost('usage_limit_per_customer') !== ''
                ? max(1, (int) $this->request->getPost('usage_limit_per_customer')) : null,
            'applies_to'               => 'all',
            'first_order_only'         => $this->request->getPost('first_order_only') !== null ? 1 : 0,
            'starts_at'                => $this->request->getPost('starts_at') ?: null,
            'ends_at'                  => $this->request->getPost('ends_at') ?: null,
            'is_active'                => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        // A percentage over 100 would pay the customer to shop.
        if ($type === 'percent' && $payload['value'] > 100) {
            return redirect()->back()->withInput()->with('error', 'A percentage discount cannot exceed 100%.');
        }

        // A window that closes before it opens can never fire.
        if ($payload['starts_at'] !== null && $payload['ends_at'] !== null
            && strtotime((string) $payload['ends_at']) < strtotime((string) $payload['starts_at'])) {
            return redirect()->back()->withInput()->with('error', 'The end date falls before the start date.');
        }

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();
        service('audit')->log($id === null ? 'created' : 'updated', 'coupons', 'coupon', $newId, $payload['code']);

        return redirect()->to(site_url('admin/coupons/' . $newId . '/edit'))->with('success', 'Coupon saved.');
    }
}
