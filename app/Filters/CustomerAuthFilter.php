<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\CustomerModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for customer account pages (orders, addresses, wishlist).
 * Guest checkout deliberately does NOT pass through here.
 */
class CustomerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session    = session();
        $customerId = $session->get('customer_id');

        if ($customerId === null) {
            $session->setFlashdata('intended_url', current_url());

            return redirect()->to('/account/login');
        }

        $customer = model(CustomerModel::class)->find((int) $customerId);

        if ($customer === null || (int) $customer['is_active'] !== 1) {
            $session->remove(['customer_id', 'customer_name']);

            return redirect()->to('/account/login')
                ->with('error', 'Please sign in again.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
