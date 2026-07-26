<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The permission catalogue.
 *
 * One place that lists every permission the application checks, grouped and
 * labelled for humans. Before this existed, permissions were bare strings
 * scattered between route definitions and controllers, and the roles screen
 * could only show a count — there was no way to build a picker, and no way to
 * tell a typo from a real permission.
 *
 * Adding a permission means adding it HERE as well as using it, or it will not
 * be assignable from the admin panel.
 */
class Permissions extends BaseConfig
{
    /**
     * group => [permission => [label, description]]
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    public array $catalogue = [
        'Orders' => [
            'orders.view'   => ['See orders', 'Open the order list and read any order.'],
            'orders.manage' => ['Fulfil orders', 'Change status, record payments and dispatch.'],
        ],
        'Enquiries' => [
            'enquiries.view'   => ['See enquiries', 'Open the enquiry pipeline.'],
            'enquiries.manage' => ['Work enquiries', 'Change stage, assign an owner, add follow-ups.'],
        ],
        'Catalogue' => [
            'products.view'     => ['See products', 'Browse the product list.'],
            'products.manage'   => ['Edit products', 'Create, change and remove products and images.'],
            'categories.manage' => ['Edit categories', 'Create, change and remove categories.'],
            'giftboxes.view'    => ['See gift boxes', 'Browse the gift box list.'],
            'giftboxes.manage'  => ['Configure gift boxes', 'Capacity, contents and pricing rules.'],
        ],
        'Selling' => [
            'coupons.manage' => ['Manage coupons', 'Create and change discount codes.'],
            'customers.view' => ['See customers', 'Read customer records and their order history.'],
        ],
        'Content' => [
            'content.manage' => ['Edit content', 'Pages, banners and email templates.'],
        ],
        'Insight' => [
            'reports.view' => ['See reports', 'Revenue, best sellers, and CSV exports.'],
        ],
        'System' => [
            'settings.view'         => ['See settings', 'Read the settings screens.'],
            'settings.manage'       => ['Change settings', 'Edit settings, including mail configuration.'],
            'settings.journey_mode' => ['Switch Buy / Enquire', 'Change how the whole store sells. Consequential.'],
            'staff.manage'          => ['Manage staff', 'Create accounts and assign roles.'],
            'roles.manage'          => ['Manage roles', 'Create roles and choose what they grant.'],
            'audit.view'            => ['See the audit log', 'Read the record of who changed what.'],
        ],
    ];

    /** Every permission as a flat list. @return list<string> */
    public function all(): array
    {
        $out = [];

        foreach ($this->catalogue as $permissions) {
            foreach (array_keys($permissions) as $permission) {
                $out[] = $permission;
            }
        }

        return $out;
    }

    public function label(string $permission): string
    {
        foreach ($this->catalogue as $permissions) {
            if (isset($permissions[$permission])) {
                return $permissions[$permission][0];
            }
        }

        return $permission;
    }

    /** Is this a permission we recognise? Guards against typos and injection. */
    public function exists(string $permission): bool
    {
        return in_array($permission, $this->all(), true);
    }
}
