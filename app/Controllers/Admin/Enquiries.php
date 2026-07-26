<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminUserModel;
use App\Models\EnquiryModel;
use App\Models\EnquiryNoteModel;
use App\Models\OrderItemComponentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * The lead pipeline. An enquiry is an order row with journey_mode =
 * 'enquire_now'; this screen works the `enquiries` record hanging off it.
 */
class Enquiries extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('enquiries.view')) {
            return $denied;
        }

        $model   = model(EnquiryModel::class);
        $filters = $this->readFilters();

        $model->select(
            'enquiries.*, orders.order_ref, orders.customer_name, orders.customer_email,'
            . ' orders.customer_phone, orders.grand_total, orders.placed_at,'
            . ' admin_users.name AS owner_name',
            false
        )
            ->join('orders', 'orders.id = enquiries.order_id', 'inner')
            ->join('admin_users', 'admin_users.id = enquiries.assigned_to_admin_id', 'left');

        if ($filters['status'] !== null) {
            $model->where('enquiries.lead_status', $filters['status']);
        }

        if ($filters['overdue']) {
            $model->whereNotIn('enquiries.lead_status', ['won', 'lost', 'spam'])
                ->where('enquiries.followup_at <', date('Y-m-d H:i:s'));
        }

        if ($filters['q'] !== null) {
            $model->groupStart()
                ->like('enquiries.enquiry_ref', $filters['q'])
                ->orLike('orders.customer_name', $filters['q'])
                ->orLike('orders.customer_email', $filters['q'])
                ->orLike('enquiries.company', $filters['q'])
                ->groupEnd();
        }

        $rows = $model->orderBy('enquiries.id', 'DESC')
            ->paginate(config(Rasmein::class)->adminPerPage);

        $model->pager->only(['q', 'status', 'overdue']);

        return $this->adminPage('admin/enquiries/index', [
            'enquiries' => $rows,
            'pager'     => $model->pager,
            'total'     => $model->pager->getTotal(),
            'filters'   => $filters,
            'statuses'  => config(Rasmein::class)->enquiryStatuses,
        ], 'Enquiries');
    }

    public function show(int $id)
    {
        if ($denied = $this->deny('enquiries.view')) {
            return $denied;
        }

        $enquiry = model(EnquiryModel::class)->find($id);

        if ($enquiry === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $order      = model(OrderModel::class)->find((int) $enquiry['order_id']);
        $items      = model(OrderItemModel::class)->forOrder((int) $enquiry['order_id']);
        $itemIds    = array_map(static fn (array $i): int => (int) $i['id'], $items);
        $components = [];

        foreach (model(OrderItemComponentModel::class)->forItems($itemIds) as $component) {
            $components[(int) $component['order_item_id']][] = $component;
        }

        return $this->adminPage('admin/enquiries/show', [
            'enquiry'    => $enquiry,
            'order'      => $order,
            'items'      => $items,
            'components' => $components,
            'notes'      => model(EnquiryNoteModel::class)->forEnquiry($id),
            'staff'      => model(AdminUserModel::class)
                ->select('id, name')->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'statuses'   => config(Rasmein::class)->enquiryStatuses,
            'canManage'  => $this->can('enquiries.manage'),
        ], 'Enquiry ' . $enquiry['enquiry_ref']);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('enquiries.manage')) {
            return $denied;
        }

        $model   = model(EnquiryModel::class);
        $enquiry = $model->find($id);

        if ($enquiry === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $statuses = config(Rasmein::class)->enquiryStatuses;
        $status   = (string) $this->request->getPost('lead_status');

        if (! array_key_exists($status, $statuses)) {
            return redirect()->back()->with('error', 'That is not a pipeline status.');
        }

        $quoted   = $this->request->getPost('quoted_value');
        $followup = $this->request->getPost('followup_at');
        $assignee = $this->request->getPost('assigned_to_admin_id');

        $update = [
            'lead_status'          => $status,
            'quoted_value'         => $quoted !== '' && $quoted !== null ? (float) $quoted : null,
            'followup_at'          => $followup !== '' && $followup !== null ? $followup : null,
            'assigned_to_admin_id' => $assignee !== '' && $assignee !== null ? (int) $assignee : null,
            'lost_reason'          => $status === 'lost'
                ? mb_substr(trim((string) $this->request->getPost('lost_reason')), 0, 255) ?: null
                : null,
        ];

        // Closing the lead stamps the time once, and reopening clears it.
        if (in_array($status, ['won', 'lost', 'spam'], true)) {
            $update['closed_at'] = $enquiry['closed_at'] ?? date('Y-m-d H:i:s');
        } else {
            $update['closed_at'] = null;
        }

        $model->update($id, $update);

        service('audit')->logChange(
            'enquiries',
            'enquiry',
            $id,
            $enquiry,
            $update,
            $enquiry['enquiry_ref'] . ' updated'
        );

        // Moving to Quoted is the moment the customer is waiting for.
        if ($status === 'quoted' && $enquiry['lead_status'] !== 'quoted') {
            $order = model(OrderModel::class)->find((int) $enquiry['order_id']);

            if ($order !== null) {
                service('notify')->enquiryQuoted($order, array_merge($enquiry, $update));
            }
        }

        return redirect()->back()->with('success', 'Enquiry updated.');
    }

    public function addNote(int $id)
    {
        if ($denied = $this->deny('enquiries.manage')) {
            return $denied;
        }

        $note = trim((string) $this->request->getPost('note'));

        if ($note === '') {
            return redirect()->back()->with('error', 'Write something first.');
        }

        $type  = (string) $this->request->getPost('note_type');
        $types = ['note', 'call', 'email', 'meeting', 'quote'];

        model(EnquiryNoteModel::class)->insert([
            'enquiry_id'    => $id,
            'admin_user_id' => session('admin_id'),
            'note'          => mb_substr($note, 0, 2000),
            'note_type'     => in_array($type, $types, true) ? $type : 'note',
        ]);

        service('audit')->log('note_added', 'enquiries', 'enquiry', $id);

        return redirect()->back()->with('success', 'Note added.');
    }

    /** @return array<string, mixed> */
    private function readFilters(): array
    {
        $status = (string) $this->request->getGet('status');

        return [
            'status'  => array_key_exists($status, config(Rasmein::class)->enquiryStatuses) ? $status : null,
            'overdue' => $this->request->getGet('overdue') !== null,
            'q'       => trim((string) $this->request->getGet('q')) ?: null,
        ];
    }
}
