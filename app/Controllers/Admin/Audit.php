<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminAuditLogModel;
use Config\Rasmein;

/** Read-only view of who changed what. Append-only by design. */
class Audit extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('audit.view')) {
            return $denied;
        }

        $model  = model(AdminAuditLogModel::class);
        $module = trim((string) $this->request->getGet('module')) ?: null;

        $model->select('admin_audit_log.*, admin_users.name AS admin_name')
            ->join('admin_users', 'admin_users.id = admin_audit_log.admin_user_id', 'left');

        if ($module !== null) {
            $model->where('admin_audit_log.module', $module);
        }

        $rows = $model->orderBy('admin_audit_log.id', 'DESC')
            ->paginate(config(Rasmein::class)->adminPerPage);

        $model->pager->only(['module']);

        return $this->adminPage('admin/audit', [
            'entries' => $rows,
            'pager'   => $model->pager,
            'total'   => $model->pager->getTotal(),
            'module'  => $module,
            'modules' => ['auth', 'orders', 'enquiries', 'settings', 'products', 'giftboxes', 'content'],
        ], 'Audit log');
    }
}
