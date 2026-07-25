<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AdminRoleModel extends Model
{
    protected $table         = 'admin_roles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['name', 'slug', 'description', 'permissions', 'is_system'];

    protected $validationRules = [
        'name' => 'required|max_length[80]',
        'slug' => 'required|max_length[60]|regex_match[/^[a-z0-9-]+$/]|is_unique[admin_roles.slug,id,{id}]',
    ];

    /**
     * Every permission the panel understands, grouped for the role editor.
     * A role's `permissions` column stores a JSON list of these keys.
     *
     * @return array<string, array<string, string>>
     */
    public static function permissionCatalogue(): array
    {
        return [
            'Catalogue' => [
                'products.view'    => 'View products',
                'products.manage'  => 'Create / edit / delete products',
                'categories.manage' => 'Manage categories & collections',
            ],
            'Gifting' => [
                'giftboxes.view'   => 'View gift boxes',
                'giftboxes.manage' => 'Configure boxes, capacity & pricing rules',
            ],
            'Sales' => [
                'orders.view'     => 'View orders',
                'orders.manage'   => 'Change order status, dispatch, invoice',
                'enquiries.view'  => 'View enquiries',
                'enquiries.manage' => 'Update enquiry pipeline & notes',
                'coupons.manage'  => 'Manage coupons & discounts',
            ],
            'People' => [
                'customers.view'   => 'View customers',
                'customers.manage' => 'Edit customer records',
                'staff.manage'     => 'Manage staff accounts & roles',
            ],
            'Content' => [
                'content.manage' => 'Manage banners & pages',
            ],
            'Insight' => [
                'reports.view' => 'View reports & exports',
            ],
            'System' => [
                'settings.view'         => 'View settings',
                'settings.manage'       => 'Change general settings',
                // Deliberately separate: this one control changes how the
                // entire store sells, so it is not bundled with the rest.
                'settings.journey_mode' => 'Switch the site between Buy and Enquire',
                'settings.payments'     => 'Change payment & bank settings',
                'audit.view'            => 'View the audit log',
            ],
        ];
    }

    /** @return list<string> */
    public static function allPermissionKeys(): array
    {
        $keys = [];

        foreach (self::permissionCatalogue() as $group) {
            $keys = array_merge($keys, array_keys($group));
        }

        return $keys;
    }
}
