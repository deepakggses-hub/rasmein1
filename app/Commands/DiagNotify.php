<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AdminNotificationModel;
use App\Models\EmailTemplateModel;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/** Exercises template rendering, notification targeting, and the mail queue. */
class DiagNotify extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-notify';
    protected $description = 'Test email template rendering and staff notifications.';

    private int $pass = 0;
    private int $fail = 0;
    private array $orders = [];

    public function run(array $params)
    {
        CLI::newLine();

        try {
            $this->section('Template rendering');
            $this->testRendering();

            $this->section('Notification targeting');
            $this->testTargeting();

            $this->section('Order raises both');
            $this->testOrderFlow();

            $this->section('Queue behaviour');
            $this->testQueue();
        } catch (Throwable $e) {
            $this->fail++;
            CLI::write('  UNCAUGHT: ' . $e->getMessage(), 'red');
            CLI::write('    ' . $e->getFile() . ':' . $e->getLine(), 'dark_gray');
        } finally {
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write(sprintf('  %d passed, %d failed', $this->pass, $this->fail), $this->fail === 0 ? 'green' : 'red');
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function section(string $t): void
    {
        CLI::write('  ' . $t, 'white');
        CLI::write('  ' . str_repeat('-', 60), 'dark_gray');
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->pass++ : $this->fail++;
        CLI::write(sprintf('  [%s] %-40s %s', $ok ? ' ok ' : 'FAIL', $label, $detail), $ok ? 'green' : 'red');
    }

    private function testRendering(): void
    {
        $model    = model(EmailTemplateModel::class);
        $template = $model->findByKey('order_placed_customer');

        $this->check('all 11 templates seeded', $model->countAllResults() >= 11, $model->countAllResults() . ' present');
        $this->check('template found by key', $template !== null);

        $rendered = service('mail')->render($template, [
            'order_ref'     => 'RSM-2026-000999',
            'customer_name' => 'Asha Patel',
            'order_total'   => rs_money(1234),
            'placed_at'     => '26 Jul 2026',
            'order_url'     => site_url('order/abc'),
        ]);

        $this->check('subject substituted', str_contains($rendered['subject'], 'RSM-2026-000999'), $rendered['subject']);
        $this->check('body substituted', str_contains($rendered['body'], 'Asha Patel'));
        $this->check('brand token works everywhere', str_contains($rendered['subject'], 'Rasmein'));
        $this->check(
            'no unreplaced braces escape',
            ! str_contains($rendered['subject'] . $rendered['body'], '{{'),
            'none left'
        );

        // The important one: a hostile value must not become markup.
        $hostile = service('mail')->render($template, [
            'customer_name' => '<script>alert(1)</script>',
            'order_ref'     => '"><img src=x onerror=alert(1)>',
            'order_total'   => rs_money(1),
            'placed_at'     => 'today',
            'order_url'     => site_url('order/x'),
        ]);

        // esc() escapes < > " ' & — it does not escape "=", so the literal
        // text "onerror=" survives harmlessly. What matters is that no LIVE
        // tag exists, which means no unescaped "<" followed by a tag name.
        $this->check(
            'no live tag survives in the body',
            preg_match('/<\\s*(script|img|iframe|svg)/i', $hostile['body']) === 0,
            'no executable markup'
        );
        $this->check(
            'escaped form is present',
            str_contains($hostile['body'], '&lt;script&gt;'),
            'rendered as text'
        );

        // An undeclared token must not be substitutable.
        $sneaky = service('mail')->render(
            ['subject' => 'Hi {{secret_key}}', 'body_html' => '<p>{{secret_key}}</p>', 'placeholders' => '{}'],
            ['secret_key' => 'SHOULD-NOT-APPEAR']
        );
        $this->check(
            'undeclared token is not substituted',
            ! str_contains($sneaky['subject'] . $sneaky['body'], 'SHOULD-NOT-APPEAR'),
            'stripped instead'
        );

        $wrapped = service('mail')->wrap('<p>Hello</p>');
        $this->check('brand shell wraps the body', str_contains($wrapped, 'Rasmein') && str_contains($wrapped, '<p>Hello</p>'));
        $this->check('plain-text alternative generated', service('mail')->toPlainText('<p>One</p><p>Two</p>') !== '');
    }

    private function testTargeting(): void
    {
        db_connect()->table('admin_notifications')->emptyTable();

        $sent = service('notify')->toStaff('test_event', 'A test notification', 'orders.view', [
            'body' => 'body text', 'severity' => 'info',
        ]);
        $this->check('notification fans out to staff', $sent > 0, $sent . ' recipient(s)');

        // A wildcard role matches every permission, including one that does
        // not exist — that is correct, and worth stating rather than assuming.
        $wild = service('notify')->toStaff('test_event', 'Wildcard', 'permission.that.does.not.exist', []);
        $this->check('super admin receives everything (wildcard)', $wild > 0, $wild . ' recipient(s)');

        // A narrow permission must reach fewer people than the wildcard does.
        db_connect()->table('admin_users')->insert([
            'role_id' => (int) db_connect()->table('admin_roles')->where('slug', 'support-staff')
                ->get()->getRowArray()['id'],
            'name' => 'Scoped Tester', 'email' => 'scoped@example.test',
            'password_hash' => password_hash('irrelevant-for-this-test', PASSWORD_DEFAULT),
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $ordersReach  = service('notify')->toStaff('t1', 'Orders', 'orders.view', []);
        $settingsReach = service('notify')->toStaff('t2', 'Settings', 'settings.journey_mode', []);
        $this->check(
            'a narrow permission reaches fewer people',
            $settingsReach < $ordersReach,
            'orders.view ' . $ordersReach . ' vs settings.journey_mode ' . $settingsReach
        );

        $adminId = (int) (db_connect()->table('admin_users')->select('id')->get()->getRowArray()['id'] ?? 0);
        $model   = model(AdminNotificationModel::class);
        $this->check('unread count reflects it', $model->unreadCount($adminId) > 0, $model->unreadCount($adminId) . ' unread');

        // Dedupe.
        service('notify')->toStaff('dupe', 'First', 'orders.view', ['dedupe_key' => 'k1']);
        $before = $model->unreadCount($adminId);
        service('notify')->toStaff('dupe', 'Second', 'orders.view', ['dedupe_key' => 'k1']);
        $this->check('dedupe key suppresses a repeat', $model->unreadCount($adminId) === $before, 'still ' . $before);

        $first = $model->where('admin_user_id', $adminId)->orderBy('id', 'DESC')->first();
        $this->check('mark read is scoped to the owner', $model->markRead((int) $first['id'], $adminId));
        $this->check('mark read refuses another owner', ! $model->markRead((int) $first['id'], 999999));

        $cleared = $model->markAllRead($adminId);
        $this->check('mark all read works', $model->unreadCount($adminId) === 0, $cleared . ' cleared');
    }

    private function testOrderFlow(): void
    {
        db_connect()->table('notification_log')->emptyTable();
        db_connect()->table('admin_notifications')->emptyTable();

        service('settings')->set('enquiry_notify_emails', ['team@example.test'], 'json');
        service('settings')->flush();

        $product = model(ProductModel::class)->scopeVisible()->where('products.stock_qty >', 3)->first();
        service('cart')->addProduct($product->id, 1);

        $result = service('orders')->placeFromCart([
            'customer_name' => 'Notify Tester', 'customer_email' => 'notify@example.test',
            'customer_phone' => '9876543210', 'ship_name' => 'Notify Tester',
            'ship_phone' => '9876543210', 'ship_line1' => '1 Mail Road',
            'ship_city' => 'Jaipur', 'ship_state' => 'Rajasthan',
            'ship_postal_code' => '302001', 'ship_country' => 'India',
            'bill_same_as_ship' => true, 'spam_score' => 0,
        ], 'notify-' . bin2hex(random_bytes(6)));

        $this->check('order placed', $result['ok'], (string) ($result['error'] ?? ''));

        if (! $result['ok']) {
            return;
        }

        $this->orders[] = (int) $result['order']['id'];
        $db = db_connect();

        $customerMail = $db->table('notification_log')->where('recipient', 'notify@example.test')->countAllResults();
        $staffMail    = $db->table('notification_log')->where('recipient', 'team@example.test')->countAllResults();
        $inApp        = $db->table('admin_notifications')->where('event', 'order_placed')->countAllResults();

        $this->check('customer email queued', $customerMail === 1, $customerMail . ' row');
        $this->check('team email queued', $staffMail === 1, $staffMail . ' row');
        $this->check('in-app notification raised', $inApp > 0, $inApp . ' row(s)');

        $row = $db->table('notification_log')->where('recipient', 'notify@example.test')->get()->getRowArray();
        $this->check('subject was rendered, not left raw', ! str_contains((string) $row['subject'], '{{'), (string) $row['subject']);
        $this->check('body was stored rendered', str_contains((string) $row['body_html'], 'Notify Tester'));
        $this->check('queued, not sent inline', $row['status'] === 'queued', $row['status']);
    }

    private function testQueue(): void
    {
        $db = db_connect();

        // Inactive templates must not queue anything.
        model(EmailTemplateModel::class)->where('template_key', 'customer_welcome')->set('is_active', 0)->update();
        $before = $db->table('notification_log')->countAllResults();
        $queued = service('mail')->queue('customer_welcome', 'someone@example.test', ['customer_name' => 'X']);
        $this->check('inactive template queues nothing', $queued === false && $db->table('notification_log')->countAllResults() === $before);
        model(EmailTemplateModel::class)->where('template_key', 'customer_welcome')->set('is_active', 1)->update();

        $this->check(
            'unknown template key is refused',
            service('mail')->queue('no_such_template', 'a@example.test') === false
        );
        $this->check(
            'invalid recipient is refused',
            service('mail')->queue('customer_welcome', 'not-an-email', ['customer_name' => 'X']) === false
        );

        // Draining with no mail server configured must fail gracefully and
        // schedule a retry rather than throw.
        $result = service('mail')->drainQueue(5);
        $this->check(
            'drain handles a dead mail server',
            is_array($result) && array_key_exists('sent', $result),
            'sent ' . $result['sent'] . ' · retrying ' . $result['skipped'] . ' · failed ' . $result['failed']
        );

        $retry = $db->table('notification_log')->where('attempts >', 0)->get()->getRowArray();

        if ($retry !== null) {
            $this->check('a failed send schedules a retry', $retry['next_attempt_at'] !== null || $retry['status'] === 'failed');
            $this->check('the reason is recorded', ! empty($retry['error']), mb_substr((string) $retry['error'], 0, 40));
        }
    }

    private function cleanup(): void
    {
        $db = db_connect();

        foreach ($this->orders as $orderId) {
            foreach (model(\App\Models\OrderItemModel::class)->forOrder($orderId) as $item) {
                if ($item['product_id'] !== null) {
                    $db->query('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [
                        (int) $item['quantity'], (int) $item['product_id'],
                    ]);
                }
            }

            $db->table('orders')->where('id', $orderId)->delete();
        }

        $db->table('admin_users')->where('email', 'scoped@example.test')->delete();

        // DELETE not TRUNCATE: MySQL refuses to truncate a table another
        // table's foreign key points at. Children before parents.
        $db->table('cart_item_components')->emptyTable();
        $db->table('cart_items')->emptyTable();
        $db->table('carts')->emptyTable();
        $db->table('notification_log')->emptyTable();
        $db->table('admin_notifications')->emptyTable();
        session()->remove('cart_uuid');
    }
}
