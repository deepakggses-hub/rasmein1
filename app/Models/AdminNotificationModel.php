<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AdminNotificationModel extends Model
{
    protected $table         = 'admin_notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'admin_user_id', 'event', 'title', 'body', 'link_url', 'severity',
        'entity_type', 'entity_id', 'dedupe_key', 'is_read', 'read_at',
    ];

    protected $validationRules = [
        'admin_user_id' => 'required|is_natural_no_zero',
        'event'         => 'required|max_length[60]',
        'title'         => 'required|max_length[191]',
        'severity'      => 'permit_empty|in_list[info,success,warning,urgent]',
    ];

    public function unreadCount(int $adminId): int
    {
        return $this->where('admin_user_id', $adminId)->where('is_read', 0)->countAllResults();
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $adminId, int $limit = 10): array
    {
        return $this->where('admin_user_id', $adminId)->orderBy('id', 'DESC')->findAll($limit);
    }

    public function markRead(int $id, int $adminId): bool
    {
        // Scoped: you can only mark your own notification read.
        if ($this->where('id', $id)->where('admin_user_id', $adminId)->first() === null) {
            return false;
        }

        return (bool) $this->update($id, ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(int $adminId): int
    {
        $count = $this->unreadCount($adminId);

        $this->where('admin_user_id', $adminId)->where('is_read', 0)
            ->set(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')])
            ->update();

        return $count;
    }

    /** Has this exact thing already been raised recently? Stops repeat spam. */
    public function recentlyRaised(string $dedupeKey, int $withinHours = 24): bool
    {
        return $this->where('dedupe_key', $dedupeKey)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $withinHours * 3600))
            ->countAllResults() > 0;
    }
}
