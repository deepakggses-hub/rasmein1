<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminRoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Permissions;

/**
 * Role creation and the permissions each grants.
 *
 * This is the most dangerous screen in the panel, because a role IS a set of
 * privileges. Five rules, all enforced here and all tested:
 *
 *  1. NO ESCALATION. You cannot put a permission into a role that your own
 *     account does not already hold. Otherwise anyone with roles.manage grants
 *     themselves everything in two clicks.
 *  2. ONLY A SUPER ADMIN GRANTS '*'. The wildcard is not offered otherwise.
 *  3. SYSTEM ROLES CANNOT BE DELETED. The seeded three are what the code and
 *     the notification targeting assume exist.
 *  4. A ROLE IN USE CANNOT BE DELETED. Accounts would be left pointing at
 *     nothing.
 *  5. PERMISSIONS COME FROM THE CATALOGUE. A posted string that is not in
 *     Config\Permissions is discarded, so a crafted form cannot invent one.
 */
class Roles extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        $roles = model(AdminRoleModel::class)->orderBy('name', 'ASC')->findAll();
        $counts = [];

        foreach (db_connect()->table('admin_users')
            ->select('role_id, COUNT(*) AS n', false)
            ->where('deleted_at', null)
            ->groupBy('role_id')->get()->getResultArray() as $row) {
            $counts[(int) $row['role_id']] = (int) $row['n'];
        }

        return $this->adminPage('admin/roles/index', [
            'roles'       => $roles,
            'counts'      => $counts,
            'catalogue'   => config(Permissions::class)->catalogue,
            'isSuper'     => $this->can('*'),
        ], 'Roles');
    }

    public function create()
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        $role = model(AdminRoleModel::class)->find($id);

        if ($role === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($role);
    }

    public function store()
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        if (model(AdminRoleModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('roles.manage')) {
            return $denied;
        }

        $model = model(AdminRoleModel::class);
        $role  = $model->find($id);

        if ($role === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ((int) ($role['is_system'] ?? 0) === 1) {
            return redirect()->back()->with('error', 'That is a built-in role and cannot be removed.');
        }

        $inUse = db_connect()->table('admin_users')
            ->where('role_id', $id)->where('deleted_at', null)->countAllResults();

        if ($inUse > 0) {
            return redirect()->back()->with(
                'error',
                $inUse . ' account' . ($inUse === 1 ? ' is' : 's are') . ' using this role. '
                    . 'Move them to another role first.'
            );
        }

        $model->delete($id);
        service('audit')->log('deleted', 'staff', 'admin_role', $id, $role['name']);

        return redirect()->to(site_url('admin/roles'))->with('success', $role['name'] . ' removed.');
    }

    // ------------------------------------------------------------------

    private function form(?array $role): string
    {
        $granted = $role !== null
            ? (json_decode((string) $role['permissions'], true) ?: [])
            : [];

        return $this->adminPage('admin/roles/form', [
            'role'       => $role,
            'granted'    => $granted,
            'catalogue'  => config(Permissions::class)->catalogue,
            // What this administrator may hand out — the picker only offers these.
            'grantable'  => $this->grantable(),
            'isSuper'    => $this->can('*'),
            'isOwnRole'  => $role !== null && (int) $role['id'] === (int) ($this->admin['role_id'] ?? 0),
            'assigned'   => $role !== null
                ? db_connect()->table('admin_users')->where('role_id', $role['id'])
                    ->where('deleted_at', null)->countAllResults()
                : 0,
        ], $role === null ? 'New role' : 'Edit ' . $role['name']);
    }

    /**
     * Permissions this administrator is allowed to grant.
     *
     * A super admin may grant anything. Anyone else may grant only what they
     * hold — which is what stops roles.manage from being a route to everything.
     *
     * @return list<string>
     */
    private function grantable(): array
    {
        $all = config(Permissions::class)->all();

        if ($this->can('*')) {
            return $all;
        }

        return array_values(array_filter($all, fn (string $p): bool => $this->can($p)));
    }

    private function save(?int $id)
    {
        $model = model(AdminRoleModel::class);
        $name  = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'A role needs a name.');
        }

        $existing = $id !== null ? $model->find($id) : null;
        $isSystem = $existing !== null && (int) ($existing['is_system'] ?? 0) === 1;

        // ---- the permissions ----
        $posted   = array_map('strval', (array) $this->request->getPost('permissions'));
        $config   = config(Permissions::class);
        $grantable = $this->grantable();

        $wantsWildcard = in_array('*', $posted, true);

        if ($wantsWildcard && ! $this->can('*')) {
            return redirect()->back()->withInput()->with(
                'error',
                'Only a super admin can create a role that grants everything.'
            );
        }

        if ($wantsWildcard) {
            $permissions = ['*'];
        } else {
            // Only real permissions, and only ones this administrator holds.
            $permissions = array_values(array_filter(
                array_unique($posted),
                static fn (string $p): bool => $config->exists($p) && in_array($p, $grantable, true)
            ));

            $refused = array_values(array_diff(
                array_filter(array_unique($posted), static fn (string $p): bool => $config->exists($p)),
                $permissions
            ));

            if ($refused !== []) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'You cannot grant permissions your own account does not hold: '
                        . implode(', ', array_map([$config, 'label'], $refused))
                );
            }
        }

        if ($permissions === []) {
            return redirect()->back()->withInput()->with('error', 'A role that grants nothing is not useful. Choose at least one permission.');
        }

        // A system role keeps its slug — the code and the seeders refer to it.
        $slug = $isSystem
            ? (string) $existing['slug']
            : strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) ($this->request->getPost('slug') ?: $name)), '-'));

        $payload = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'permissions' => json_encode($permissions, JSON_UNESCAPED_SLASHES),
        ];

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();

        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'staff',
            'admin_role',
            $newId,
            $name . ' — ' . count($permissions) . ' permission(s)',
            $existing !== null ? ['permissions' => $existing['permissions']] : [],
            ['permissions' => $payload['permissions']]
        );

        // A role change alters what its holders can reach, including possibly
        // this administrator. The auth filter refreshes cached permissions on
        // every request, so this takes effect immediately.
        return redirect()->to(site_url('admin/roles/' . $newId . '/edit'))
            ->with('success', 'Role saved. Anyone holding it sees the change on their next page load.');
    }
}
