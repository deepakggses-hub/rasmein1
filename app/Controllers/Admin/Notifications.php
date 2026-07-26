<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminNotificationModel;
use Config\Rasmein;

/**
 * The staff notification centre.
 *
 * Everything is scoped to the signed-in account — notifications are created one
 * row per recipient, so there is no shared row anyone could read by guessing
 * an id.
 */
class Notifications extends AdminController
{
    public function index(): string
    {
        $model = model(AdminNotificationModel::class);
        $id    = (int) session('admin_id');
        $show  = (string) $this->request->getGet('show');

        $model->where('admin_user_id', $id);

        if ($show === 'unread') {
            $model->where('is_read', 0);
        }

        $rows = $model->orderBy('id', 'DESC')->paginate(config(Rasmein::class)->adminPerPage);
        $model->pager->only(['show']);

        return $this->adminPage('admin/notifications/index', [
            'notifications' => $rows,
            'pager'         => $model->pager,
            'total'         => $model->pager->getTotal(),
            'unread'        => model(AdminNotificationModel::class)->unreadCount($id),
            'show'          => $show === 'unread' ? 'unread' : 'all',
        ], 'Notifications');
    }

    public function read(int $id)
    {
        model(AdminNotificationModel::class)->markRead($id, (int) session('admin_id'));

        // Follow the notification through to whatever it is about.
        $target = (string) $this->request->getPost('link');

        if ($target !== '' && str_starts_with($target, site_url())) {
            return redirect()->to($target);
        }

        return redirect()->back();
    }

    public function readAll()
    {
        $count = model(AdminNotificationModel::class)->markAllRead((int) session('admin_id'));

        return redirect()->back()->with(
            'success',
            $count === 0 ? 'Nothing was unread.' : $count . ' marked as read.'
        );
    }
}
