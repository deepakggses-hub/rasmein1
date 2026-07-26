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
    /** URL-safe token for an email address. */
    public static function encodeRef(string $email): string
    {
        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
    }

    /** Reverse of encodeRef. Null when the token is not decodable. */
    public static function decodeRef(string $token): ?string
    {
        $padded  = strtr($token, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    public function index()
    {
        if ($denied = $this->deny('customers.view')) {
            return $denied;
        }

        $q    = trim((string) $this->request->getGet('q')) ?: null;
        $db   = db_connect();
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = config(Rasmein::class)->adminPerPage;

        /*
         * Customers come from TWO sources, unioned.
         *
         * Most people buying a gift check out as guests, so a list built only
         * from the `customers` table would miss the majority. But building it
         * only from orders — which is what this did first — hides anyone who
         * registered and has not bought yet, and they are exactly the people
         * worth following up. Reported from the field: a newly registered
         * account was invisible here.
         *
         * So: every address that has ordered, plus every registered account,
         * folded together on email.
         */
        $sql = 'SELECT
                    email,
                    MAX(name) AS name,
                    MAX(phone) AS phone,
                    SUM(is_order) AS orders,
                    SUM(spend) AS spend,
                    SUM(is_enquiry) AS enquiries,
                    MIN(seen_at) AS first_seen,
                    MAX(seen_at) AS last_seen,
                    MAX(customer_id) AS customer_id
                FROM (
                    SELECT
                        o.customer_email AS email,
                        o.customer_name  AS name,
                        o.customer_phone AS phone,
                        CASE WHEN o.journey_mode = ? AND o.status NOT IN ("cancelled","refunded") THEN 1 ELSE 0 END AS is_order,
                        CASE WHEN o.journey_mode = ? AND o.status NOT IN ("cancelled","refunded") THEN o.grand_total ELSE 0 END AS spend,
                        CASE WHEN o.journey_mode = ? THEN 1 ELSE 0 END AS is_enquiry,
                        o.placed_at AS seen_at,
                        NULL AS customer_id
                    FROM orders o
                    WHERE o.deleted_at IS NULL

                    UNION ALL

                    SELECT
                        c.email, c.name, c.phone,
                        0, 0, 0,
                        c.created_at AS seen_at,
                        c.id AS customer_id
                    FROM customers c
                    WHERE c.deleted_at IS NULL
                ) AS everyone';

        $binds = [Rasmein::MODE_BUY, Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE];

        if ($q !== null) {
            // Bound, not interpolated — this is user input reaching raw SQL.
            $sql .= ' WHERE email LIKE ? OR name LIKE ? OR phone LIKE ?';
            $like = '%' . $q . '%';
            array_push($binds, $like, $like, $like);
        }

        $sql .= ' GROUP BY email';

        $totalRows = (int) ($db->query(
            'SELECT COUNT(*) AS c FROM (' . $sql . ') AS counted',
            $binds
        )->getRowArray()['c'] ?? 0);

        $rows = $db->query(
            $sql . ' ORDER BY spend DESC, last_seen DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) (($page - 1) * $per),
            $binds
        )->getResultArray();

        return $this->adminPage('admin/customers/index', [
            'customers' => $rows,
            'total'     => $totalRows,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($totalRows / $per)),
            'q'         => $q,
        ], 'Customers');
    }

    /**
     * Customer detail, addressed by an opaque token rather than the email.
     *
     * Two reasons. CodeIgniter's permittedURIChars does not include "@", so an
     * email in the path was rejected with a 400 — this page had never opened.
     * And an email in a URL ends up in access logs, browser history and Referer
     * headers, which is careless with someone's personal data when an
     * unambiguous alternative costs nothing.
     */
    public function show(string $token)
    {
        if ($denied = $this->deny('customers.view')) {
            return $denied;
        }

        $email = self::decodeRef($token);

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(site_url('admin/customers'))->with('error', 'That customer reference is not valid.');
        }
        $db    = db_connect();

        $orders = $db->table('orders')
            ->select('id, order_ref, journey_mode, status, payment_status, grand_total, placed_at')
            ->where('customer_email', $email)
            ->where('deleted_at', null)
            ->orderBy('placed_at', 'DESC')
            ->get()->getResultArray();

        $account = $db->table('customers')->where('email', $email)->get()->getRowArray();

        // Someone may have an account and no orders yet — that is a real
        // customer record and the page must still open.
        if ($orders === [] && $account === null) {
            return redirect()->to(site_url('admin/customers'))->with('error', 'No record for that address.');
        }

        return $this->adminPage('admin/customers/show', [
            'email'    => $email,
            'orders'   => $orders,
            'account'  => $account,
            'spend'    => array_sum(array_map(
                static fn (array $o): float => $o['journey_mode'] === 'buy_now'
                    && ! in_array($o['status'], ['cancelled', 'refunded'], true)
                    ? (float) $o['grand_total'] : 0.0,
                $orders
            )),
        ], $email);
    }
}
