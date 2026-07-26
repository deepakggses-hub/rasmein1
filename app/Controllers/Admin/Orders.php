<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\EnquiryModel;
use App\Models\OrderItemComponentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\ShipmentModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * Order fulfilment.
 *
 * Status changes go through a whitelist of legal transitions rather than
 * accepting any value that arrives in a form. That stops a stale tab moving a
 * delivered order back to pending, and makes the history meaningful.
 */
class Orders extends AdminController
{
    /**
     * Which statuses may follow which. A terminal status has no exits — undoing
     * a cancellation is a deliberate act, not a dropdown.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending'    => ['confirmed', 'cancelled'],
        'confirmed'  => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed'     => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        'delivered'  => ['refunded'],
        'cancelled'  => [],
        'refunded'   => [],
    ];

    public function index()
    {
        if ($denied = $this->deny('orders.view')) {
            return $denied;
        }

        $orders  = model(OrderModel::class);
        $filters = $this->readFilters();

        $orders->select('orders.*')->where('orders.journey_mode', Rasmein::MODE_BUY);

        if ($filters['status'] !== null) {
            $orders->where('orders.status', $filters['status']);
        }

        if ($filters['payment'] !== null) {
            $orders->where('orders.payment_status', $filters['payment']);
        }

        if ($filters['q'] !== null) {
            $orders->groupStart()
                ->like('orders.order_ref', $filters['q'])
                ->orLike('orders.customer_name', $filters['q'])
                ->orLike('orders.customer_email', $filters['q'])
                ->orLike('orders.customer_phone', $filters['q'])
                ->groupEnd();
        }

        $perPage = config(Rasmein::class)->adminPerPage;
        $rows    = $orders->orderBy('orders.id', 'DESC')->paginate($perPage);

        $orders->pager->only(['q', 'status', 'payment']);

        return $this->adminPage('admin/orders/index', [
            'orders'   => $rows,
            'pager'    => $orders->pager,
            'total'    => $orders->pager->getTotal(),
            'filters'  => $filters,
            'statuses' => config(Rasmein::class)->orderStatuses,
            'payments' => config(Rasmein::class)->paymentStatuses,
        ], 'Orders');
    }

    public function show(int $id)
    {
        if ($denied = $this->deny('orders.view')) {
            return $denied;
        }

        $order = model(OrderModel::class)->find($id);

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $items      = model(OrderItemModel::class)->forOrder($id);
        $itemIds    = array_map(static fn (array $i): int => (int) $i['id'], $items);
        $components = [];

        foreach (model(OrderItemComponentModel::class)->forItems($itemIds) as $component) {
            $components[(int) $component['order_item_id']][] = $component;
        }

        return $this->adminPage('admin/orders/show', [
            'order'       => $order,
            'items'       => $items,
            'components'  => $components,
            'history'     => model(OrderStatusHistoryModel::class)->forOrder($id),
            'shipment'    => model(ShipmentModel::class)->latestForOrder($id),
            'enquiry'     => $order['journey_mode'] === Rasmein::MODE_ENQUIRE
                ? model(EnquiryModel::class)->findByOrderId($id)
                : null,
            'nextStates'  => self::TRANSITIONS[$order['status']] ?? [],
            'statuses'    => config(Rasmein::class)->orderStatuses,
            'payments'    => config(Rasmein::class)->paymentStatuses,
            'canManage'   => $this->can('orders.manage'),
        ], 'Order ' . $order['order_ref']);
    }

    public function updateStatus(int $id)
    {
        if ($denied = $this->deny('orders.manage')) {
            return $denied;
        }

        $orders = model(OrderModel::class);
        $order  = $orders->find($id);

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $to   = (string) $this->request->getPost('status');
        $note = $this->request->getPost('note');
        $from = (string) $order['status'];

        // The whitelist, not the form, decides what is possible.
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return redirect()->back()->with(
                'error',
                'An order that is ' . $from . ' cannot move to ' . $to . '.'
            );
        }

        $update = ['status' => $to];

        $stamps = [
            'confirmed'  => 'confirmed_at',
            'dispatched' => 'dispatched_at',
            'delivered'  => 'delivered_at',
            'cancelled'  => 'cancelled_at',
        ];

        if (isset($stamps[$to])) {
            $update[$stamps[$to]] = date('Y-m-d H:i:s');
        }

        $orders->update($id, $update);
        model(OrderStatusHistoryModel::class)->record($id, $from, $to, $note !== '' ? $note : null);

        service('audit')->log(
            'status_changed',
            'orders',
            'order',
            $id,
            $order['order_ref'] . ': ' . $from . ' → ' . $to,
            ['status' => $from],
            ['status' => $to]
        );

        service('notify')->orderStatusChanged($orders->find($id), $from, $to);

        return redirect()->back()->with('success', 'Order is now ' . $to . '.');
    }

    public function updatePayment(int $id)
    {
        if ($denied = $this->deny('orders.manage')) {
            return $denied;
        }

        $orders = model(OrderModel::class);
        $order  = $orders->find($id);

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $to = (string) $this->request->getPost('payment_status');

        if (! array_key_exists($to, config(Rasmein::class)->paymentStatuses)) {
            return redirect()->back()->with('error', 'That is not a payment status.');
        }

        $orders->update($id, [
            'payment_status' => $to,
            'payment_method' => $this->request->getPost('payment_method') ?: $order['payment_method'],
        ]);

        // Payment state is money, so it is always audited with before and after.
        service('audit')->log(
            'payment_updated',
            'orders',
            'order',
            $id,
            $order['order_ref'] . ' marked ' . $to,
            ['payment_status' => $order['payment_status']],
            ['payment_status' => $to]
        );

        return redirect()->back()->with('success', 'Payment recorded as ' . $to . '.');
    }

    /** Manual dispatch: courier and tracking, entered by hand. */
    public function dispatch(int $id)
    {
        if ($denied = $this->deny('orders.manage')) {
            return $denied;
        }

        $order = model(OrderModel::class)->find($id);

        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'courier_name'    => ['label' => 'Courier', 'rules' => 'required|max_length[120]'],
            'tracking_number' => ['label' => 'Tracking number', 'rules' => 'required|max_length[120]'],
            'tracking_url'    => ['label' => 'Tracking link', 'rules' => 'permit_empty|valid_url_strict|max_length[255]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        model(ShipmentModel::class)->insert([
            'order_id'             => $id,
            'courier_name'         => $this->request->getPost('courier_name'),
            'tracking_number'      => $this->request->getPost('tracking_number'),
            'tracking_url'         => $this->request->getPost('tracking_url') ?: null,
            'dispatched_at'        => date('Y-m-d H:i:s'),
            'note'                 => $this->request->getPost('note') ?: null,
            'created_by_admin_id'  => session('admin_id'),
        ]);

        if (in_array('dispatched', self::TRANSITIONS[$order['status']] ?? [], true)) {
            model(OrderModel::class)->update($id, [
                'status'        => 'dispatched',
                'dispatched_at' => date('Y-m-d H:i:s'),
            ]);
            model(OrderStatusHistoryModel::class)->record($id, $order['status'], 'dispatched', 'Dispatched with tracking');
        }

        service('audit')->log('dispatched', 'orders', 'order', $id, $order['order_ref'] . ' dispatched');

        service('notify')->orderDispatched(
            model(OrderModel::class)->find($id),
            model(ShipmentModel::class)->latestForOrder($id) ?? []
        );

        return redirect()->back()->with('success', 'Dispatch recorded.');
    }

    public function addNote(int $id)
    {
        if ($denied = $this->deny('orders.manage')) {
            return $denied;
        }

        $note = trim((string) $this->request->getPost('admin_note'));

        model(OrderModel::class)->update($id, ['admin_note' => $note !== '' ? mb_substr($note, 0, 2000) : null]);
        service('audit')->log('note_updated', 'orders', 'order', $id);

        return redirect()->back()->with('success', 'Note saved.');
    }

    /** @return array<string, string|null> */
    private function readFilters(): array
    {
        $status  = (string) $this->request->getGet('status');
        $payment = (string) $this->request->getGet('payment');
        $config  = config(Rasmein::class);

        return [
            'status'  => array_key_exists($status, $config->orderStatuses) ? $status : null,
            'payment' => array_key_exists($payment, $config->paymentStatuses) ? $payment : null,
            'q'       => trim((string) $this->request->getGet('q')) ?: null,
        ];
    }
}
