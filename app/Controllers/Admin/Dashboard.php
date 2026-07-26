<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminAuditLogModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use Config\Rasmein;

/**
 * What someone opening the panel at 9am actually needs: what came in, what is
 * waiting on them, and what is about to run out.
 */
class Dashboard extends AdminController
{
    public function index(): string
    {
        $db = db_connect();

        return $this->adminPage('admin/dashboard', [
            'today'       => $this->totals($db, 'today'),
            'week'        => $this->totals($db, 'week'),
            'month'       => $this->totals($db, 'month'),
            'needsWork'   => $this->needsAttention($db),
            'trend'       => $this->trend($db, 14),
            'catalogue'   => $this->catalogueHealth($db),
            'people'      => $this->people($db),
            'pipeline'    => $this->pipeline($db),
            'baskets'     => $this->baskets($db),
            'topProducts' => $this->topProducts($db),
            'mail'        => $this->mailHealth($db),
            'lowStock'    => $this->lowStock(),
            'recent'      => $this->recentOrders(),
            'audit'       => $this->can('audit.view')
                ? model(AdminAuditLogModel::class)->recent(8)
                : [],
        ], 'Dashboard');
    }

    /**
     * Revenue and order count per day, for the sparkline.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trend(\CodeIgniter\Database\BaseConnection $db, int $days): array
    {
        $rows = [];

        foreach ($db->table('orders')
            ->select('DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS revenue', false)
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->where('placed_at >=', date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')))
            ->groupBy('day')->get()->getResultArray() as $row) {
            $rows[$row['day']] = $row;
        }

        // Fill the gaps: a day with no orders is information, and a sparkline
        // with missing days lies about the shape.
        $out = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));

            $out[] = [
                'day'     => $day,
                'orders'  => (int) ($rows[$day]['orders'] ?? 0),
                'revenue' => (float) ($rows[$day]['revenue'] ?? 0),
            ];
        }

        return $out;
    }

    /** @return array<string, int> */
    private function catalogueHealth(\CodeIgniter\Database\BaseConnection $db): array
    {
        $products = $db->table('products')->where('deleted_at', null);

        return [
            'products'    => (clone $products)->countAllResults(),
            'live'        => $db->table('products')->where('deleted_at', null)->where('is_active', 1)->countAllResults(),
            'hidden'      => $db->table('products')->where('deleted_at', null)->where('is_active', 0)->countAllResults(),
            'out'         => $db->table('products')->where('deleted_at', null)->where('is_active', 1)
                ->where('track_inventory', 1)->where('stock_qty', 0)->countAllResults(),
            'low'         => $db->table('products')->where('deleted_at', null)->where('is_active', 1)
                ->where('track_inventory', 1)->where('stock_qty >', 0)
                ->where('stock_qty <= low_stock_threshold', null, false)->countAllResults(),
            'categories'  => $db->table('categories')->where('deleted_at', null)->countAllResults(),
            'giftBoxes'   => $db->table('gift_boxes')->where('deleted_at', null)->where('is_active', 1)->countAllResults(),
            'noImage'     => $db->table('products')
                ->where('products.deleted_at', null)
                ->where('NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = products.id)', null, false)
                ->countAllResults(),
        ];
    }

    /** @return array<string, mixed> */
    private function people(\CodeIgniter\Database\BaseConnection $db): array
    {
        $monthStart = date('Y-m-01 00:00:00');

        // Distinct buyers, counted from orders so guests are included — most
        // gifting customers never make an account.
        $buyers = (int) ($db->query(
            'SELECT COUNT(DISTINCT customer_email) AS n FROM orders
             WHERE journey_mode = ? AND status NOT IN ("cancelled","refunded") AND deleted_at IS NULL',
            [Rasmein::MODE_BUY]
        )->getRowArray()['n'] ?? 0);

        $repeat = (int) ($db->query(
            'SELECT COUNT(*) AS n FROM (
                SELECT customer_email FROM orders
                WHERE journey_mode = ? AND status NOT IN ("cancelled","refunded") AND deleted_at IS NULL
                GROUP BY customer_email HAVING COUNT(*) > 1
             ) AS repeaters',
            [Rasmein::MODE_BUY]
        )->getRowArray()['n'] ?? 0);

        return [
            'buyers'      => $buyers,
            'repeat'      => $repeat,
            'repeatRate'  => $buyers > 0 ? (int) round($repeat / $buyers * 100) : 0,
            'accounts'    => $db->table('customers')->where('deleted_at', null)->countAllResults(),
            'newAccounts' => $db->table('customers')->where('deleted_at', null)
                ->where('created_at >=', $monthStart)->countAllResults(),
            'staff'       => $db->table('admin_users')->where('deleted_at', null)->where('is_active', 1)->countAllResults(),
        ];
    }

    /** @return array<string, mixed> */
    private function pipeline(\CodeIgniter\Database\BaseConnection $db): array
    {
        $stages = [];
        $total  = 0;
        $value  = 0.0;

        foreach ($db->table('enquiries')
            ->select('lead_status, COUNT(*) AS n, COALESCE(SUM(COALESCE(quoted_value, estimated_value)),0) AS v', false)
            ->where('deleted_at', null)
            ->groupBy('lead_status')->get()->getResultArray() as $row) {
            $stages[$row['lead_status']] = ['count' => (int) $row['n'], 'value' => (float) $row['v']];
            $total += (int) $row['n'];

            if (! in_array($row['lead_status'], ['won', 'lost', 'spam'], true)) {
                $value += (float) $row['v'];
            }
        }

        $won = $stages['won']['count'] ?? 0;

        return [
            'stages'   => $stages,
            'total'    => $total,
            'open'     => $value,
            'won'      => $won,
            'winRate'  => $total > 0 ? (int) round($won / $total * 100) : 0,
        ];
    }

    /** @return array<string, int> */
    private function baskets(\CodeIgniter\Database\BaseConnection $db): array
    {
        return [
            'active'    => $db->table('carts')->where('status', 'active')
                ->where('last_activity_at >=', date('Y-m-d H:i:s', strtotime('-2 hours')))->countAllResults(),
            'idle'      => $db->table('carts')->where('status', 'active')
                ->where('last_activity_at <', date('Y-m-d H:i:s', strtotime('-2 hours')))->countAllResults(),
            'abandoned' => $db->table('carts')->where('status', 'abandoned')->countAllResults(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function topProducts(\CodeIgniter\Database\BaseConnection $db): array
    {
        return $db->table('order_items')
            ->select('order_items.name_snapshot AS name, SUM(order_items.quantity) AS units,'
                . ' SUM(order_items.line_total) AS revenue', false)
            ->join('orders', 'orders.id = order_items.order_id')
            ->where('orders.journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->where('orders.deleted_at', null)
            ->where('orders.placed_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->groupBy('order_items.name_snapshot')
            ->orderBy('units', 'DESC')
            ->get(5)->getResultArray();
    }

    /** @return array<string, mixed> */
    private function mailHealth(\CodeIgniter\Database\BaseConnection $db): array
    {
        $counts = ['queued' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($db->table('notification_log')->select('status, COUNT(*) AS n', false)
            ->where('channel', 'email')->groupBy('status')->get()->getResultArray() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['n'];
            }
        }

        $configured = false;

        try {
            $protocol = $db->table('settings')->select('value')
                ->where('key_name', 'mail_protocol')->get()->getRowArray()['value'] ?? '';
            $host = $db->table('settings')->select('value')
                ->where('key_name', 'mail_smtp_host')->get()->getRowArray()['value'] ?? '';
            $google = $db->table('settings')->select('value')
                ->where('key_name', 'mail_google_refresh_token')->get()->getRowArray()['value'] ?? '';

            $configured = ($protocol === 'smtp' && $host !== '')
                || ($protocol === 'gmail_api' && $google !== '')
                || in_array($protocol, ['mail', 'sendmail'], true);
        } catch (\Throwable) {
            $configured = false;
        }

        return $counts + ['configured' => $configured];
    }

    /**
     * Money and counts for a window. Cancelled and refunded orders are excluded
     * from revenue — counting them would flatter the number.
     *
     * @return array<string, mixed>
     */
    private function totals(\CodeIgniter\Database\BaseConnection $db, string $window): array
    {
        $since = match ($window) {
            'today' => date('Y-m-d 00:00:00'),
            'week'  => date('Y-m-d H:i:s', strtotime('-7 days')),
            default => date('Y-m-d H:i:s', strtotime('-30 days')),
        };

        $revenue = (float) ($db->table('orders')
            ->selectSum('grand_total', 'total')
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->get()->getRowArray()['total'] ?? 0);

        $orders = $db->table('orders')
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->countAllResults();

        $enquiries = $db->table('orders')
            ->where('journey_mode', Rasmein::MODE_ENQUIRE)
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->countAllResults();

        return [
            'label'     => match ($window) { 'today' => 'Today', 'week' => 'Last 7 days', default => 'Last 30 days' },
            'revenue'   => $revenue,
            'orders'    => $orders,
            'enquiries' => $enquiries,
            'average'   => $orders > 0 ? $revenue / $orders : 0.0,
        ];
    }

    /** @return array<string, int> */
    private function needsAttention(\CodeIgniter\Database\BaseConnection $db): array
    {
        return [
            'pending_orders'   => $db->table('orders')->where('journey_mode', Rasmein::MODE_BUY)
                ->where('status', 'pending')->where('deleted_at', null)->countAllResults(),
            'unpaid_orders'    => $db->table('orders')->where('journey_mode', Rasmein::MODE_BUY)
                ->whereIn('payment_status', ['unpaid', 'pending'])
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->where('deleted_at', null)->countAllResults(),
            'to_dispatch'      => $db->table('orders')->where('journey_mode', Rasmein::MODE_BUY)
                ->whereIn('status', ['confirmed', 'processing', 'packed'])
                ->where('deleted_at', null)->countAllResults(),
            'new_enquiries'    => $db->table('enquiries')->where('lead_status', 'new')
                ->where('deleted_at', null)->countAllResults(),
            'unread_notices'   => $this->unreadNotifications(),
            'overdue_followup' => $db->table('enquiries')
                ->whereNotIn('lead_status', ['won', 'lost', 'spam'])
                ->where('followup_at <', date('Y-m-d H:i:s'))
                ->where('deleted_at', null)->countAllResults(),
        ];
    }

    /** @return array<int, \App\Entities\Product> */
    private function lowStock(): array
    {
        return model(ProductModel::class)
            ->where('is_active', 1)
            ->where('track_inventory', 1)
            ->where('stock_qty <= low_stock_threshold', null, false)
            ->orderBy('stock_qty', 'ASC')
            ->findAll(8);
    }

    /** @return array<int, array<string, mixed>> */
    private function recentOrders(): array
    {
        return model(OrderModel::class)
            ->select('id, uuid, order_ref, journey_mode, status, payment_status, customer_name, grand_total, placed_at')
            ->orderBy('id', 'DESC')
            ->findAll(8);
    }
}
