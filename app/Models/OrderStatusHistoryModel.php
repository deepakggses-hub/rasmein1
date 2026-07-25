<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OrderStatusHistoryModel extends Model
{
    protected $table         = 'order_status_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'order_id', 'from_status', 'to_status', 'note',
        'changed_by_admin_id', 'notified_customer',
    ];

    public function record(int $orderId, ?string $from, string $to, ?string $note = null): void
    {
        $this->insert([
            'order_id'            => $orderId,
            'from_status'         => $from,
            'to_status'           => $to,
            'note'                => $note,
            'changed_by_admin_id' => session('admin_id'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->where('order_id', $orderId)->orderBy('id', 'ASC')->findAll();
    }
}
