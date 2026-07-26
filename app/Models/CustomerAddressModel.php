<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class CustomerAddressModel extends Model
{
    protected $table          = 'customer_addresses';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'customer_id', 'label', 'recipient_name', 'phone', 'line1', 'line2',
        'landmark', 'city', 'state', 'postal_code', 'country',
        'is_default_shipping', 'is_default_billing',
    ];

    protected $validationRules = [
        'id'             => 'permit_empty|is_natural_no_zero',
        'customer_id'    => 'required|is_natural_no_zero',
        'recipient_name' => 'required|min_length[2]|max_length[120]',
        'phone'          => 'required|min_length[10]|max_length[20]',
        'line1'          => 'required|max_length[191]',
        'city'           => 'required|max_length[80]',
        'state'          => 'required|max_length[80]',
        'postal_code'    => 'required|regex_match[/^[1-9][0-9]{5}$/]',
        'label'          => 'permit_empty|max_length[40]',
    ];

    protected $validationMessages = [
        'postal_code' => ['regex_match' => 'A PIN code is six digits and cannot start with zero.'],
    ];

    /** @return array<int, array<string, mixed>> */
    public function forCustomer(int $customerId): array
    {
        return $this->where('customer_id', $customerId)
            ->orderBy('is_default_shipping', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Scoped read. A guessed id belonging to someone else resolves to null
     * rather than being readable or editable (CLAUDE.md §6).
     */
    public function findForCustomer(int $id, int $customerId): ?array
    {
        return $this->where('id', $id)->where('customer_id', $customerId)->first();
    }

    /** Exactly one default of each kind per customer. */
    public function makeDefault(int $customerId, int $addressId, string $kind = 'shipping'): void
    {
        $column = $kind === 'billing' ? 'is_default_billing' : 'is_default_shipping';

        $this->where('customer_id', $customerId)->set($column, 0)->update();
        $this->update($addressId, [$column => 1]);
    }
}
