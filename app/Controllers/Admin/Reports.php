<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Config\Rasmein;

/**
 * Reporting and exports.
 *
 * Every figure excludes cancelled and refunded orders, and every export goes
 * through CsvExporter, which neutralises cells a spreadsheet would otherwise
 * treat as formulas.
 */
class Reports extends AdminController
{
    /** Windows offered, and how far back each reaches. */
    private const RANGES = [
        '7'   => 'Last 7 days',
        '30'  => 'Last 30 days',
        '90'  => 'Last 90 days',
        '365' => 'Last 12 months',
    ];

    public function index()
    {
        if ($denied = $this->deny('reports.view')) {
            return $denied;
        }

        $days  = $this->readRange();
        $since = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));
        $db    = db_connect();

        return $this->adminPage('admin/reports/index', [
            'days'        => $days,
            'ranges'      => self::RANGES,
            'since'       => $since,
            'summary'     => $this->summary($db, $since),
            'daily'       => $this->daily($db, $since),
            'topProducts' => $this->topProducts($db, $since),
            'byCategory'  => $this->byCategory($db, $since),
            'pipeline'    => $this->pipeline($db, $since),
            'coupons'     => $this->couponUse($db, $since),
            'needsCharts' => true,
        ], 'Reports');
    }

    // =================================================================
    // Exports
    // =================================================================

    public function export(string $what)
    {
        if ($denied = $this->deny('reports.view')) {
            return $denied;
        }

        $days  = $this->readRange();
        $since = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));
        $stamp = date('Y-m-d');

        // The dataset is chosen from a fixed list — never from the URL directly.
        return match ($what) {
            'orders'    => $this->exportOrders($since, $stamp),
            'enquiries' => $this->exportEnquiries($since, $stamp),
            'products'  => $this->exportProducts($stamp),
            'customers' => $this->exportCustomers($stamp),
            default     => redirect()->to(site_url('admin/reports'))->with('error', 'Unknown export.'),
        };
    }

    private function exportOrders(string $since, string $stamp): void
    {
        $rows = db_connect()->table('orders')
            ->select('order_ref, placed_at, status, payment_status, customer_name, customer_email,'
                . ' customer_phone, ship_city, ship_state, ship_postal_code, coupon_code,'
                . ' subtotal, discount_total, shipping_total, grand_total')
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->orderBy('placed_at', 'DESC')
            ->get()->getResultArray();

        service('audit')->log('exported', 'reports', 'orders', null, count($rows) . ' orders');

        service('csv')->stream('rasmein-orders-' . $stamp . '.csv', [
            'Reference', 'Placed', 'Status', 'Payment', 'Customer', 'Email', 'Phone',
            'City', 'State', 'PIN', 'Coupon', 'Subtotal', 'Discount', 'Delivery', 'Total',
        ], $rows);

        exit;
    }

    private function exportEnquiries(string $since, string $stamp): void
    {
        $rows = db_connect()->table('enquiries')
            ->select('enquiries.enquiry_ref, orders.placed_at, enquiries.lead_status,'
                . ' orders.customer_name, orders.customer_email, orders.customer_phone,'
                . ' enquiries.company, enquiries.expected_quantity, enquiries.preferred_contact,'
                . ' enquiries.estimated_value, enquiries.quoted_value, enquiries.followup_at,'
                . ' admin_users.name AS owner, enquiries.requirement_note')
            ->join('orders', 'orders.id = enquiries.order_id')
            ->join('admin_users', 'admin_users.id = enquiries.assigned_to_admin_id', 'left')
            ->where('enquiries.deleted_at', null)
            ->where('orders.placed_at >=', $since)
            ->orderBy('orders.placed_at', 'DESC')
            ->get()->getResultArray();

        service('audit')->log('exported', 'reports', 'enquiries', null, count($rows) . ' enquiries');

        service('csv')->stream('rasmein-enquiries-' . $stamp . '.csv', [
            'Reference', 'Received', 'Stage', 'Contact', 'Email', 'Phone', 'Company',
            'Quantity wanted', 'Prefers', 'Estimated', 'Quoted', 'Follow up', 'Owner', 'Brief',
        ], $rows);

        exit;
    }

    private function exportProducts(string $stamp): void
    {
        $rows = db_connect()->table('products')
            ->select('products.sku, products.name, categories.name AS category, products.price,'
                . ' products.compare_at_price, products.stock_qty, products.low_stock_threshold,'
                . ' products.track_inventory, products.giftbox_slots, products.sale_mode, products.is_active')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->where('products.deleted_at', null)
            ->orderBy('products.name', 'ASC')
            ->get()->getResultArray();

        service('audit')->log('exported', 'reports', 'products', null, count($rows) . ' products');

        service('csv')->stream('rasmein-products-' . $stamp . '.csv', [
            'SKU', 'Name', 'Category', 'Price', 'Was', 'Stock', 'Low at',
            'Tracked', 'Box slots', 'Journey', 'Live',
        ], $rows);

        exit;
    }

    private function exportCustomers(string $stamp): void
    {
        // Aggregated from orders, so a guest who never made an account is
        // still counted — they are a customer regardless.
        $rows = db_connect()->table('orders')
            ->select('customer_email, MAX(customer_name) AS name, MAX(customer_phone) AS phone,'
                . ' COUNT(*) AS orders, SUM(grand_total) AS spend, MAX(placed_at) AS last_order', false)
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->groupBy('customer_email')
            ->orderBy('spend', 'DESC')
            ->get()->getResultArray();

        service('audit')->log('exported', 'reports', 'customers', null, count($rows) . ' customers');

        service('csv')->stream('rasmein-customers-' . $stamp . '.csv', [
            'Email', 'Name', 'Phone', 'Orders', 'Total spend', 'Last order',
        ], $rows);

        exit;
    }

    // =================================================================
    // Figures
    // =================================================================

    private function readRange(): int
    {
        $days = (string) $this->request->getGet('days');

        return array_key_exists($days, self::RANGES) ? (int) $days : 30;
    }

    /** @return array<string, mixed> */
    private function summary($db, string $since): array
    {
        $paid = $db->table('orders')
            ->select('COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS revenue,'
                . ' COALESCE(SUM(discount_total), 0) AS discounts', false)
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->get()->getRowArray();

        $enquiries = $db->table('enquiries')
            ->join('orders', 'orders.id = enquiries.order_id')
            ->where('orders.placed_at >=', $since)
            ->where('enquiries.deleted_at', null)
            ->countAllResults();

        $won = $db->table('enquiries')
            ->join('orders', 'orders.id = enquiries.order_id')
            ->where('orders.placed_at >=', $since)
            ->where('enquiries.lead_status', 'won')
            ->where('enquiries.deleted_at', null)
            ->countAllResults();

        $orders = (int) $paid['orders'];

        return [
            'orders'    => $orders,
            'revenue'   => (float) $paid['revenue'],
            'discounts' => (float) $paid['discounts'],
            'average'   => $orders > 0 ? (float) $paid['revenue'] / $orders : 0.0,
            'enquiries' => $enquiries,
            'won'       => $won,
            'winRate'   => $enquiries > 0 ? round($won / $enquiries * 100) : 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function daily($db, string $since): array
    {
        return $db->table('orders')
            ->select('DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS revenue', false)
            ->where('journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('deleted_at', null)
            ->where('placed_at >=', $since)
            ->groupBy('day')->orderBy('day', 'ASC')
            ->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function topProducts($db, string $since): array
    {
        return $db->table('order_items')
            ->select('order_items.name_snapshot AS name, order_items.sku_snapshot AS sku,'
                . ' SUM(order_items.quantity) AS units, SUM(order_items.line_total) AS revenue', false)
            ->join('orders', 'orders.id = order_items.order_id')
            ->where('orders.journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->where('orders.deleted_at', null)
            ->where('orders.placed_at >=', $since)
            ->groupBy('order_items.name_snapshot, order_items.sku_snapshot')
            ->orderBy('units', 'DESC')
            ->get(10)->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function byCategory($db, string $since): array
    {
        return $db->table('order_items')
            ->select('COALESCE(categories.name, "Uncategorised") AS category,'
                . ' SUM(order_items.quantity) AS units, SUM(order_items.line_total) AS revenue', false)
            ->join('orders', 'orders.id = order_items.order_id')
            ->join('products', 'products.id = order_items.product_id', 'left')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->where('orders.journey_mode', Rasmein::MODE_BUY)
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->where('orders.deleted_at', null)
            ->where('orders.placed_at >=', $since)
            ->groupBy('category')->orderBy('revenue', 'DESC')
            ->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function pipeline($db, string $since): array
    {
        return $db->table('enquiries')
            ->select('enquiries.lead_status, COUNT(*) AS count,'
                . ' COALESCE(SUM(COALESCE(enquiries.quoted_value, enquiries.estimated_value)),0) AS value', false)
            ->join('orders', 'orders.id = enquiries.order_id')
            ->where('orders.placed_at >=', $since)
            ->where('enquiries.deleted_at', null)
            ->groupBy('enquiries.lead_status')
            ->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function couponUse($db, string $since): array
    {
        return $db->table('coupon_redemptions')
            ->select('coupons.code, COUNT(*) AS uses, COALESCE(SUM(coupon_redemptions.discount_amount),0) AS given', false)
            ->join('coupons', 'coupons.id = coupon_redemptions.coupon_id')
            ->where('coupon_redemptions.created_at >=', $since)
            ->groupBy('coupons.code')->orderBy('uses', 'DESC')
            ->get(10)->getResultArray();
    }
}
