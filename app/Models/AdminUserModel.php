<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Staff accounts.
 *
 * Passwords never enter this model in plain form — callers pass
 * `password_hash` already hashed, or use setPassword(). There is no
 * `password` field in $allowedFields, so a stray form post cannot write one.
 */
class AdminUserModel extends Model
{
    protected $table          = 'admin_users';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'role_id', 'name', 'email', 'phone', 'password_hash',
        'is_active', 'must_change_password', 'two_factor_enabled',
        'two_factor_secret', 'last_login_at', 'last_login_ip',
    ];

    protected $validationRules = [
        'role_id' => 'required|is_natural_no_zero',
        'name'    => 'required|min_length[2]|max_length[120]',
        'email'   => 'required|valid_email|max_length[191]|is_unique[admin_users.email,id,{id}]',
        'phone'   => 'permit_empty|max_length[20]',
    ];

    /** Hash a plain password. Never store or log the plain value. */
    public function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /**
     * Fetch a staff account together with its role and decoded permissions.
     *
     * @return array<string, mixed>|null
     */
    public function withRole(int $id): ?array
    {
        $row = $this->select('admin_users.*, admin_roles.slug AS role_slug, admin_roles.name AS role_name, admin_roles.permissions AS role_permissions')
            ->join('admin_roles', 'admin_roles.id = admin_users.role_id', 'left')
            ->where('admin_users.id', $id)
            ->asArray()
            ->first();

        if ($row === null) {
            return null;
        }

        $row['permissions'] = json_decode((string) ($row['role_permissions'] ?? '[]'), true) ?: [];

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->where('email', $email)
            ->where('is_active', 1)
            ->asArray()
            ->first();
    }

    /**
     * Permission check. The wildcard '*' is the super-admin grant.
     *
     * @param array<string, mixed> $admin An account as returned by withRole()
     */
    public function can(array $admin, string $permission): bool
    {
        $permissions = $admin['permissions'] ?? [];

        if (! is_array($permissions)) {
            return false;
        }

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function recordLogin(int $id, string $ip): void
    {
        $this->update($id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);
    }
}
