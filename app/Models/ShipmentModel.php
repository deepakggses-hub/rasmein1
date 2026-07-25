<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ShipmentModel extends Model
{
    protected $table         = 'shipments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'order_id', 'courier_name', 'tracking_number', 'tracking_url',
        'packed_at', 'dispatched_at', 'delivered_at', 'note', 'created_by_admin_id',
    ];

    protected $validationRules = [
        'order_id'        => 'required|is_natural_no_zero',
        'courier_name'    => 'permit_empty|max_length[120]',
        'tracking_number' => 'permit_empty|max_length[120]',
        'tracking_url'    => 'permit_empty|max_length[255]',
    ];

    public function latestForOrder(int $orderId): ?array
    {
        return $this->where('order_id', $orderId)->orderBy('id', 'DESC')->first();
    }
}
