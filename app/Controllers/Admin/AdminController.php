<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Base for every admin screen.
 *
 * Authentication is handled by AdminAuthFilter on the route group. This class
 * adds the second half: per-action AUTHORISATION, checked in the controller
 * rather than by hiding a nav link (CLAUDE.md §6).
 */
abstract class AdminController extends BaseController
{
    /** @var array<string, mixed>|null The signed-in staff account. */
    protected ?array $admin = null;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);

        $adminId = session('admin_id');

        if ($adminId !== null) {
            $this->admin = model(AdminUserModel::class)->withRole((int) $adminId);
        }
    }

    /** Does the signed-in user hold this permission? */
    protected function can(string $permission): bool
    {
        if ($this->admin === null) {
            return false;
        }

        return model(AdminUserModel::class)->can($this->admin, $permission);
    }

    /**
     * Stop the action unless the user holds the permission.
     * Returns a redirect to be returned by the caller, or null to continue.
     */
    protected function deny(string $permission): ?RedirectResponse
    {
        if ($this->can($permission)) {
            return null;
        }

        return redirect()->to(site_url('admin'))
            ->with('error', 'You do not have access to that.');
    }

    /**
     * Render an admin screen inside the admin layout.
     *
     * @param array<string, mixed> $data
     */
    protected function adminPage(string $view, array $data = [], string $title = 'Admin'): string
    {
        return view($view, array_merge([
            'brand'       => $this->brand,
            'admin'       => $this->admin,
            'pageTitle'   => $title,
            'journeyMode' => $this->settings->journeyMode(),
            'nav'         => $this->navigation(),
        ], $data));
    }

    /**
     * The nav, filtered to what this user may actually reach. Hiding a link is
     * not access control — the controllers check too — but showing a link that
     * leads to a refusal is bad design.
     *
     * @return array<int, array{group: string, items: array<int, array{label: string, url: string, match: string, badge?: int}>}>
     */
    private function navigation(): array
    {
        $groups = [
            [
                'group' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'url' => 'admin', 'match' => 'admin', 'permission' => null],
                ],
            ],
            [
                'group' => 'Sales',
                'items' => [
                    ['label' => 'Orders', 'url' => 'admin/orders', 'match' => 'admin/orders', 'permission' => 'orders.view'],
                    ['label' => 'Enquiries', 'url' => 'admin/enquiries', 'match' => 'admin/enquiries', 'permission' => 'enquiries.view'],
                    ['label' => 'Coupons', 'url' => 'admin/coupons', 'match' => 'admin/coupons', 'permission' => 'coupons.manage'],
                ],
            ],
            [
                'group' => 'Catalogue',
                'items' => [
                    ['label' => 'Products', 'url' => 'admin/products', 'match' => 'admin/products', 'permission' => 'products.view'],
                    ['label' => 'Categories', 'url' => 'admin/categories', 'match' => 'admin/categories', 'permission' => 'products.view'],
                    ['label' => 'Gift boxes', 'url' => 'admin/gift-boxes', 'match' => 'admin/gift-boxes', 'permission' => 'giftboxes.view'],
                ],
            ],
            [
                'group' => 'Content',
                'items' => [
                    ['label' => 'Pages', 'url' => 'admin/pages', 'match' => 'admin/pages', 'permission' => 'content.manage'],
                ],
            ],
            [
                'group' => 'System',
                'items' => [
                    ['label' => 'Settings', 'url' => 'admin/settings', 'match' => 'admin/settings', 'permission' => 'settings.view'],
                    ['label' => 'Audit log', 'url' => 'admin/audit', 'match' => 'admin/audit', 'permission' => 'audit.view'],
                ],
            ],
        ];

        $out = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => $item['permission'] === null || $this->can($item['permission'])
            ));

            if ($items !== []) {
                $out[] = ['group' => $group['group'], 'items' => $items];
            }
        }

        return $out;
    }
}
