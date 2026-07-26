<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Config\Rasmein;

/**
 * Customers, assembled from orders rather than from the accounts table.
 *
 * Most people who buy from a gifting shop never make an account — they check
 * out as a guest. A customer list built only from `customers` would miss the
 * majority of them, so this groups orders by email and treats that as the
 * identity. Registered accounts are matched in where they exist.
 *
 * Read-only. Editing someone's details after the fact would desynchronise the
 * order snapshots, which have to stay as they were at purchase.
 */
class Customers extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('customers.view')) {
            return $denied;
        }

        $q    = trim((string) $this->request->getGet('q')) ?: null;
        $db   = db_connect();
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = config(Rasmein::class)->adminPerPage;

        $builder = $db->table('orders')
            ->select('orders.customer_email AS email,'
                . ' MAX(orders.customer_name) AS name,'
                . ' MAX(orders.customer_phone) AS phone,'
                . ' SUM(CASE WHEN orders.journey_mode = "buy_now" AND orders.status NOT IN ("cancelled","refunded") THEN 1 ELSE 0 END) AS orders,'
                . ' SUM(CASE WHEN orders.journey_mode = "buy_now" AND orders.status NOT IN ("cancelled","refunded") THEN orders.grand_total ELSE 0 END) AS spend,'
                . ' SUM(CASE WHEN orders.journey_mode = "enquire_now" THEN 1 ELSE 0 END) AS enquiries,'
                . ' MIN(orders.placed_at) AS first_seen,'
                . ' MAX(orders.placed_at) AS last_seen,'
                . ' MAX(customers.id) AS customer_id', false)
            ->join('customers', 'customers.email = orders.customer_email', 'left')
            ->where('orders.deleted_at', null)
            ->groupBy('orders.customer_email');

        if ($q !== null) {
            $builder->groupStart()
                ->like('orders.customer_email', $q)
                ->orLike('orders.customer_name', $q)
                ->orLike('orders.customer_phone', $q)
                ->groupEnd();
        }

        // GROUP BY makes CI4's pager unreliable here, so count and slice by hand.
        // getCompiledSelect(false) keeps the builder's state for the real query.
        $totalRows = (int) ($db->query(
            'SELECT COUNT(*) AS c FROM (' . $builder->getCompiledSelect(false) . ') AS grouped'
        )->getRowArray()['c'] ?? 0);

        $rows = $builder->orderBy('spend', 'DESC')->get($per, ($page - 1) * $per)->getResultArray();

        return $this->adminPage('admin/customers/index', [
            'customers' => $rows,
            'total'     => $totalRows,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($totalRows / $per)),
            'q'         => $q,
        ], 'Customers');
    }

    public function show(string $email)
    {
        if ($denied = $this->deny('customers.view')) {
            return $denied;
        }

        $email = trim(urldecode($email));
        $db    = db_connect();

        $orders = $db->table('orders')
            ->select('id, order_ref, journey_mode, status, payment_status, grand_total, placed_at')
            ->where('customer_email', $email)
            ->where('deleted_at', null)
            ->orderBy('placed_at', 'DESC')
            ->get()->getResultArray();

        if ($orders === []) {
            return redirect()->to(site_url('admin/customers'))->with('error', 'No record for that address.');
        }

        return $this->adminPage('admin/customers/show', [
            'email'    => $email,
            'orders'   => $orders,
            'account'  => $db->table('customers')->where('email', $email)->get()->getRowArray(),
            'spend'    => array_sum(array_map(
                static fn (array $o): float => $o['journey_mode'] === 'buy_now'
                    && ! in_array($o['status'], ['cancelled', 'refunded'], true)
                    ? (float) $o['grand_total'] : 0.0,
                $orders
            )),
        ], $email);
    }
}
