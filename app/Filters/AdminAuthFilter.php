<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\AdminUserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for every /admin route.
 *
 * Checks, in order: authenticated → account still active → holds the
 * permission this route requires. Authorisation lives here (and in the
 * controllers), never in whether a nav link was rendered.
 *
 * Usage in Routes.php:
 *   ['filter' => 'adminAuth']                     // any signed-in staff
 *   ['filter' => 'adminAuth:settings.journeyMode'] // needs that permission
 */
class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $adminId = $session->get('admin_id');

        if ($adminId === null) {
            $session->setFlashdata('intended_url', current_url());

            return redirect()->to('/admin/login');
        }

        $admin = model(AdminUserModel::class)->withRole((int) $adminId);

        if ($admin === null || (int) $admin['is_active'] !== 1) {
            $session->remove(['admin_id', 'admin_name', 'admin_role', 'admin_permissions']);
            $session->setFlashdata('error', 'Your session has ended. Sign in again.');

            return redirect()->to('/admin/login');
        }

        // Refresh cached identity so a permission change takes effect at once.
        $session->set([
            'admin_name'        => $admin['name'],
            'admin_role'        => $admin['role_slug'],
            'admin_permissions' => $admin['permissions'],
        ]);

        $required = $arguments[0] ?? null;

        if ($required !== null && ! model(AdminUserModel::class)->can($admin, $required)) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['status' => 'error', 'message' => 'You do not have access to this.']);
            }

            return redirect()->to('/admin')
                ->with('error', 'You do not have access to that area.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Admin pages must never be cached by a browser or proxy.
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->setHeader('Pragma', 'no-cache');

        return $response;
    }
}
