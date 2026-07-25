<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class CartModel extends Model
{
    protected $table         = 'carts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'uuid', 'customer_id', 'session_id', 'status', 'currency',
        'coupon_code', 'last_activity_at', 'converted_order_id',
    ];

    protected $validationRules = [
        'uuid'   => 'required|max_length[36]',
        'status' => 'required|in_list[active,converted,abandoned]',
    ];

    public function findActiveByUuid(string $uuid): ?array
    {
        return $this->where('uuid', $uuid)->where('status', 'active')->first();
    }

    public function findActiveForCustomer(int $customerId): ?array
    {
        return $this->where('customer_id', $customerId)
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function touch(int $cartId): void
    {
        $this->update($cartId, ['last_activity_at' => date('Y-m-d H:i:s')]);
    }
}
