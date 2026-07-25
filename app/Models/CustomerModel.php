<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Customer accounts. `password_hash` is nullable so guest checkout can leave
 * a contact record the shopper may later claim by setting a password.
 */
class CustomerModel extends Model
{
    protected $table          = 'customers';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name', 'email', 'phone', 'password_hash', 'email_verified_at',
        'marketing_opt_in', 'is_active', 'last_login_at', 'notes',
    ];

    protected $validationRules = [
        'name'  => 'required|min_length[2]|max_length[120]',
        'email' => 'required|valid_email|max_length[191]|is_unique[customers.email,id,{id}]',
        'phone' => 'permit_empty|min_length[10]|max_length[20]',
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => 'An account already exists with that email address.',
        ],
    ];

    public function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }
}
