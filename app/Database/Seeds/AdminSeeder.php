<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Models\AdminRoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Staff roles and the first administrator.
 *
 * The password is generated at random and printed once. It is never written
 * to a file, committed, or logged — and the account is flagged
 * must_change_password so it cannot stay as issued.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $roles = [
            [
                'name'        => 'Super Admin',
                'slug'        => 'super-admin',
                'description' => 'Full access, including the journey switch and payment settings.',
                'permissions' => ['*'],
                'is_system'   => 1,
            ],
            [
                'name'        => 'Store Manager',
                'slug'        => 'store-manager',
                'description' => 'Runs the catalogue and fulfils orders. Cannot change the journey switch or payment settings.',
                'permissions' => [
                    'products.view', 'products.manage', 'categories.manage',
                    'giftboxes.view', 'giftboxes.manage',
                    'orders.view', 'orders.manage',
                    'enquiries.view', 'enquiries.manage',
                    'coupons.manage', 'customers.view',
                    'content.manage', 'reports.view', 'settings.view',
                ],
                'is_system'   => 1,
            ],
            [
                'name'        => 'Support Staff',
                'slug'        => 'support-staff',
                'description' => 'Answers customers and follows up enquiries. Read-only on the catalogue.',
                'permissions' => [
                    'products.view', 'giftboxes.view',
                    'orders.view', 'enquiries.view', 'enquiries.manage',
                    'customers.view',
                ],
                'is_system'   => 1,
            ],
        ];

        $roleIds = [];

        foreach ($roles as $role) {
            $existing = $this->db->table('admin_roles')->where('slug', $role['slug'])->get()->getRowArray();

            if ($existing !== null) {
                $roleIds[$role['slug']] = (int) $existing['id'];
                continue;
            }

            $this->db->table('admin_roles')->insert([
                'name'        => $role['name'],
                'slug'        => $role['slug'],
                'description' => $role['description'],
                'permissions' => json_encode($role['permissions']),
                'is_system'   => $role['is_system'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $roleIds[$role['slug']] = (int) $this->db->insertID();
        }

        echo '  Roles: ' . count($roleIds) . " in place.\n";

        // ------------------------------------------------ first admin
        $email  = 'admin@rasmein.com';
        $exists = $this->db->table('admin_users')->where('email', $email)->countAllResults() > 0;

        if ($exists) {
            echo "  Admin: {$email} already exists — left untouched.\n";

            return;
        }

        // 18 bytes of CSPRNG entropy, base64 → ~24 chars.
        $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');

        $this->db->table('admin_users')->insert([
            'role_id'              => $roleIds['super-admin'],
            'name'                 => 'Rasmein Admin',
            'email'                => $email,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'is_active'            => 1,
            'must_change_password' => 1,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        echo "\n";
        echo "  ┌─────────────────────────────────────────────────────────────┐\n";
        echo "  │  ADMIN ACCOUNT CREATED — copy this now, it is shown once.   │\n";
        echo "  ├─────────────────────────────────────────────────────────────┤\n";
        echo '  │  Email     ' . str_pad($email, 49) . "│\n";
        echo '  │  Password  ' . str_pad($password, 49) . "│\n";
        echo "  ├─────────────────────────────────────────────────────────────┤\n";
        echo "  │  You will be asked to change it on first sign-in.           │\n";
        echo "  └─────────────────────────────────────────────────────────────┘\n\n";
    }
}
