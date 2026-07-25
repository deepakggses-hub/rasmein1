<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AdminAuditLogModel extends Model
{
    protected $table              = 'admin_audit_log';
    protected $primaryKey         = 'id';
    protected $returnType         = 'array';
    protected $useTimestamps      = true;
    protected $updatedField       = '';   // append-only: never updated

    protected $allowedFields = [
        'admin_user_id', 'action', 'module', 'entity_type', 'entity_id',
        'summary', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $validationRules = [
        'action' => 'required|max_length[80]',
        'module' => 'required|max_length[60]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->select('admin_audit_log.*, admin_users.name AS admin_name')
            ->join('admin_users', 'admin_users.id = admin_audit_log.admin_user_id', 'left')
            ->orderBy('admin_audit_log.id', 'DESC')
            ->findAll($limit);
    }
}
