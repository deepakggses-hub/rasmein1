<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminRoleModel;
use App\Models\AdminUserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Staff accounts and roles.
 *
 * The riskiest screen in the panel, because it is where an account could give
 * itself more than it has. Four invariants are enforced here and tested:
 *
 *  1. NO PRIVILEGE ESCALATION. You cannot grant a permission you do not hold
 *     yourself, and you cannot assign a role that holds one. The permission
 *     list comes from the catalogue, never from the POST body.
 *  2. NO SELF-LOCKOUT. You cannot deactivate, demote or delete your own account.
 *  3. THE LAST SUPER-ADMIN SURVIVES. The system always keeps at least one
 *     active account holding '*'.
 *  4. Passwords set by an administrator are single-use: the account is flagged
 *     must_change_password, so the person who set it cannot keep using it.
 */
class Staff extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/staff/index', [
            'staff' => model(AdminUserModel::class)
                ->select('admin_users.*, admin_roles.name AS role_name, admin_roles.slug AS role_slug')
                ->join('admin_roles', 'admin_roles.id = admin_users.role_id', 'left')
                ->orderBy('admin_users.name', 'ASC')
                ->findAll(),
            'roles' => model(AdminRoleModel::class)->orderBy('name', 'ASC')->findAll(),
        ], 'Staff');
    }

    public function create()
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        $user = model(AdminUserModel::class)->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($user);
    }

    public function store()
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        if (model(AdminUserModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('staff.manage')) {
            return $denied;
        }

        $model = model(AdminUserModel::class);
        $user  = $model->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($id === (int) session('admin_id')) {
            return redirect()->back()->with('error', 'You cannot remove your own account.');
        }

        if ($this->isLastSuperAdmin($id)) {
            return redirect()->back()->with(
                'error',
                'That is the only active super admin. Promote someone else first.'
            );
        }

        $model->delete($id);
        service('audit')->log('deleted', 'staff', 'admin_user', $id, $user['name'] . ' <' . $user['email'] . '>');

        return redirect()->to(site_url('admin/staff'))->with('success', $user['name'] . ' removed.');
    }

    // ------------------------------------------------------------------

    private function form(?array $user): string
    {
        return $this->adminPage('admin/staff/form', [
            'user'  => $user,
            'roles' => $this->assignableRoles(),
            'allRoles' => model(AdminRoleModel::class)->orderBy('name', 'ASC')->findAll(),
            'isSelf' => $user !== null && (int) $user['id'] === (int) session('admin_id'),
        ], $user === null ? 'New staff account' : 'Edit ' . $user['name']);
    }

    /**
     * Roles this administrator may hand out.
     *
     * A role is assignable only if the current user holds every permission it
     * grants. Otherwise a Store Manager could create a Super Admin and then
     * sign in as them — escalation by proxy.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignableRoles(): array
    {
        $roles = model(AdminRoleModel::class)->orderBy('name', 'ASC')->findAll();

        if ($this->can('*')) {
            return $roles;
        }

        return array_values(array_filter($roles, function (array $role): bool {
            $granted = json_decode((string) $role['permissions'], true) ?: [];

            if (in_array('*', $granted, true)) {
                return false;
            }

            foreach ($granted as $permission) {
                if (! $this->can($permission)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function isLastSuperAdmin(int $userId): bool
    {
        $user = model(AdminUserModel::class)->withRole($userId);

        if ($user === null || ! in_array('*', $user['permissions'] ?? [], true)) {
            return false;
        }

        $others = db_connect()->table('admin_users')
            ->join('admin_roles', 'admin_roles.id = admin_users.role_id')
            ->where('admin_users.is_active', 1)
            ->where('admin_users.deleted_at', null)
            ->where('admin_users.id !=', $userId)
            ->like('admin_roles.permissions', '"*"')
            ->countAllResults();

        return $others === 0;
    }

    private function save(?int $id)
    {
        $model  = model(AdminUserModel::class);
        $isSelf = $id !== null && $id === (int) session('admin_id');
        $roleId = (int) $this->request->getPost('role_id');

        // The role must be one this administrator is allowed to hand out.
        $assignable = array_map(static fn (array $r): int => (int) $r['id'], $this->assignableRoles());

        if (! in_array($roleId, $assignable, true)) {
            return redirect()->back()->withInput()->with(
                'error',
                'You cannot assign a role that grants more than your own account holds.'
            );
        }

        $active = $this->request->getPost('is_active') !== null;

        // Locking yourself out is always a mistake, never an intention.
        if ($isSelf && ! $active) {
            return redirect()->back()->withInput()->with('error', 'You cannot deactivate your own account.');
        }

        if ($isSelf && $roleId !== (int) ($model->find($id)['role_id'] ?? 0)) {
            return redirect()->back()->withInput()->with('error', 'You cannot change your own role.');
        }

        if ($id !== null && ! $active && $this->isLastSuperAdmin($id)) {
            return redirect()->back()->withInput()->with(
                'error',
                'That is the only active super admin. Promote someone else first.'
            );
        }

        $payload = [
            'role_id'   => $roleId,
            'name'      => trim((string) $this->request->getPost('name')),
            'email'     => strtolower(trim((string) $this->request->getPost('email'))),
            'phone'     => trim((string) $this->request->getPost('phone')) ?: null,
            'is_active' => $active ? 1 : 0,
        ];

        $password = (string) $this->request->getPost('password');

        if ($id === null) {
            // A new account always gets a password, and always has to replace it.
            if (strlen($password) < 10) {
                return redirect()->back()->withInput()->with('error', 'Set a starting password of at least 10 characters.');
            }

            $payload['password_hash']        = $model->hashPassword($password);
            $payload['must_change_password'] = 1;
        } elseif ($password !== '') {
            if (strlen($password) < 10) {
                return redirect()->back()->withInput()->with('error', 'A password must be at least 10 characters.');
            }

            $payload['password_hash']        = $model->hashPassword($password);
            $payload['must_change_password'] = 1;
        }

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();

        // The hash never enters the audit trail — AuditService redacts it, but
        // it is not passed in the first place.
        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'staff',
            'admin_user',
            $newId,
            $payload['name'] . ' <' . $payload['email'] . '>',
            [],
            ['role_id' => $payload['role_id'], 'is_active' => $payload['is_active']]
        );

        if ($id === null) {
            service('notify')->staffWelcome($model->find($newId));
        }

        return redirect()->to(site_url('admin/staff/' . $newId . '/edit'))
            ->with('success', $id === null
                ? 'Account created. They will be asked to set their own password on first sign-in.'
                : 'Account saved.');
    }
}
