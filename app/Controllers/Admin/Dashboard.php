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
            'today'      => $this->totals($db, 'today'),
            'week'       => $this->totals($db, 'week'),
            'month'      => $this->totals($db, 'month'),
            'needsWork'  => $this->needsAttention($db),
            'lowStock'   => $this->lowStock(),
            'recent'     => $this->recentOrders(),
            'audit'      => $this->can('audit.view')
                ? model(AdminAuditLogModel::class)->recent(8)
                : [],
        ], 'Dashboard');
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
