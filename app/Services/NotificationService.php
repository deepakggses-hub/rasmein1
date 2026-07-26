<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminNotificationModel;
use App\Models\AdminUserModel;
use App\Models\ProductModel;
use Throwable;

/**
 * The single place anything announces itself.
 *
 * Every event raises two things where appropriate: an in-app notification for
 * the staff who can act on it, and an email built from an editable template.
 * Callers say what happened; this decides who hears about it and how.
 *
 * Notifications are TARGETED BY PERMISSION — an order notification goes to
 * people holding `orders.view`, not to everyone. A support account that cannot
 * open the settings screen should not be told the journey mode changed.
 *
 * Nothing here is allowed to break the thing that triggered it. An order must
 * not fail because a notification could not be written, so every path is
 * wrapped and logged.
 */
class NotificationService
{
    /**
     * Raise an in-app notification for every active admin holding a permission.
     *
     * @param array<string, mixed> $options link, severity, entity_type,
     *                                      entity_id, dedupe_key, body
     */
    public function toStaff(string $event, string $title, string $permission, array $options = []): int
    {
        try {
            $model = model(AdminNotificationModel::class);

            // A repeating condition (a product still low on stock) should not
            // fill the list every time a cron runs.
            $dedupe = $options['dedupe_key'] ?? null;

            if ($dedupe !== null && $model->recentlyRaised($dedupe)) {
                return 0;
            }

            $recipients = $this->staffWith($permission);
            $now        = date('Y-m-d H:i:s');
            $rows       = [];

            foreach ($recipients as $adminId) {
                $rows[] = [
                    'admin_user_id' => $adminId,
                    'event'         => $event,
                    'title'         => mb_substr($title, 0, 191),
                    'body'          => isset($options['body']) ? mb_substr((string) $options['body'], 0, 500) : null,
                    'link_url'      => $options['link'] ?? null,
                    'severity'      => $options['severity'] ?? 'info',
                    'entity_type'   => $options['entity_type'] ?? null,
                    'entity_id'     => $options['entity_id'] ?? null,
                    'dedupe_key'    => $dedupe,
                    'is_read'       => 0,
                    'created_at'    => $now,
                ];
            }

            if ($rows === []) {
                return 0;
            }

            db_connect()->table('admin_notifications')->insertBatch($rows);

            return count($rows);
        } catch (Throwable $e) {
            log_message('error', 'Staff notification "{event}" failed: {msg}', [
                'event' => $event, 'msg' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Active staff who hold a permission.
     *
     * @return list<int>
     */
    private function staffWith(string $permission): array
    {
        $users = model(AdminUserModel::class)
            ->select('admin_users.id, admin_roles.permissions')
            ->join('admin_roles', 'admin_roles.id = admin_users.role_id', 'left')
            ->where('admin_users.is_active', 1)
            ->findAll();

        $ids = [];

        foreach ($users as $user) {
            $granted = json_decode((string) ($user['permissions'] ?? '[]'), true) ?: [];

            if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
                $ids[] = (int) $user['id'];
            }
        }

        return $ids;
    }

    // =================================================================
    // The events themselves
    // =================================================================

    /** @param array<string, mixed> $order */
    public function orderPlaced(array $order): void
    {
        $isEnquiry = $order['journey_mode'] === 'enquire_now';
        $reference = (string) $order['order_ref'];

        $this->toStaff(
            $isEnquiry ? 'enquiry_received' : 'order_placed',
            $isEnquiry
                ? 'New enquiry from ' . $order['customer_name']
                : 'New order ' . $reference . ' — ' . rs_money($order['grand_total']),
            $isEnquiry ? 'enquiries.view' : 'orders.view',
            [
                'body'        => $order['customer_name'] . ' · ' . rs_money($order['grand_total']),
                'link'        => $isEnquiry ? site_url('admin/enquiries') : site_url('admin/orders/' . $order['id']),
                'severity'    => $isEnquiry ? 'warning' : 'success',
                'entity_type' => 'order',
                'entity_id'   => (int) $order['id'],
            ]
        );

        $tokens = $this->orderTokens($order);

        service('mail')->queue(
            $isEnquiry ? 'enquiry_received_customer' : 'order_placed_customer',
            (string) $order['customer_email'],
            $tokens,
            (int) $order['id']
        );

        foreach ($this->staffEmails() as $address) {
            service('mail')->queue(
                $isEnquiry ? 'enquiry_received_admin' : 'order_placed_admin',
                $address,
                $tokens,
                (int) $order['id']
            );
        }
    }

    /** @param array<string, mixed> $order */
    public function orderStatusChanged(array $order, string $from, string $to): void
    {
        // Only the transitions a customer actually cares about get an email.
        $template = match ($to) {
            'confirmed'  => 'order_confirmed_customer',
            'dispatched' => 'order_dispatched_customer',
            'delivered'  => 'order_delivered_customer',
            'cancelled'  => 'order_cancelled_customer',
            default      => null,
        };

        if ($template === null) {
            return;
        }

        service('mail')->queue(
            $template,
            (string) $order['customer_email'],
            $this->orderTokens($order),
            (int) $order['id']
        );
    }

    /** @param array<string, mixed> $order */
    public function orderDispatched(array $order, array $shipment): void
    {
        service('mail')->queue(
            'order_dispatched_customer',
            (string) $order['customer_email'],
            array_merge($this->orderTokens($order), [
                'courier_name'    => $shipment['courier_name'] ?? '',
                'tracking_number' => $shipment['tracking_number'] ?? '',
                'tracking_url'    => $shipment['tracking_url'] ?? '',
            ]),
            (int) $order['id']
        );
    }

    public function customerWelcome(array $customer): void
    {
        service('mail')->queue('customer_welcome', (string) $customer['email'], [
            'customer_name' => $customer['name'],
        ], (int) $customer['id'], 'customer');
    }

    public function passwordReset(string $email, string $name, string $link): void
    {
        service('mail')->queue('customer_password_reset', $email, [
            'customer_name' => $name,
            'reset_url'     => $link,
            'expiry_hours'  => '1',
        ], null, 'customer');
    }

    public function registerAttempt(string $email, string $name): void
    {
        service('mail')->queue('customer_register_attempt', $email, [
            'customer_name' => $name,
        ], null, 'customer');
    }

    /** Raised by the scheduled task, not inline — stock changes constantly. */
    public function lowStockSweep(): int
    {
        $raised = 0;

        $low = model(ProductModel::class)
            ->where('is_active', 1)
            ->where('track_inventory', 1)
            ->where('stock_qty <= low_stock_threshold', null, false)
            ->orderBy('stock_qty', 'ASC')
            ->findAll(25);

        foreach ($low as $product) {
            $raised += $this->toStaff(
                'low_stock',
                $product->stock_qty === 0
                    ? $product->name . ' has sold out'
                    : $product->name . ' is down to ' . $product->stock_qty,
                'products.view',
                [
                    'body'        => 'SKU ' . $product->sku . ' · threshold ' . $product->low_stock_threshold,
                    'link'        => site_url('admin/products/' . $product->id . '/edit'),
                    'severity'    => $product->stock_qty === 0 ? 'urgent' : 'warning',
                    'entity_type' => 'product',
                    'entity_id'   => (int) $product->id,
                    // One alert per product per day, however often this runs.
                    'dedupe_key'  => 'low_stock:' . $product->id . ':' . date('Y-m-d'),
                ]
            );
        }

        return $raised;
    }

    /** @param array<string, mixed> $order @return array<string, mixed> */
    private function orderTokens(array $order): array
    {
        return [
            'order_ref'      => $order['order_ref'],
            'customer_name'  => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'order_total'    => rs_money($order['grand_total']),
            'order_subtotal' => rs_money($order['subtotal']),
            'order_status'   => $order['status'],
            'placed_at'      => date('j M Y', strtotime((string) ($order['placed_at'] ?? 'now'))),
            'order_url'      => site_url('order/' . $order['uuid']),
            'admin_url'      => site_url('admin/orders/' . $order['id']),
            'ship_name'      => $order['ship_name'] ?? '',
            'ship_address'   => trim(implode(', ', array_filter([
                $order['ship_line1'] ?? null, $order['ship_line2'] ?? null,
                $order['ship_city'] ?? null, $order['ship_state'] ?? null,
                $order['ship_postal_code'] ?? null,
            ]))),
        ];
    }

    /** @return list<string> */
    private function staffEmails(): array
    {
        $configured = service('settings')->get('enquiry_notify_emails', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $configured),
            static fn (string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
