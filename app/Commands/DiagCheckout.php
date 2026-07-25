<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * End-to-end exercise of cart → pricing → order.
 *
 *   php spark rasmein:diag-checkout
 *
 * Covers the paths that matter and are easy to get wrong: server-side pricing,
 * coupon validation branches, stock clamping, the Buy/Enquire switch,
 * idempotency, and rollback on failure. Test data is cleaned up afterwards.
 */
class DiagCheckout extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-checkout';
    protected $description = 'End-to-end test of cart, pricing, coupons and order creation.';

    private int $pass = 0;
    private int $fail = 0;

    /** @var list<int> */
    private array $createdOrders = [];

    public function run(array $params)
    {
        CLI::newLine();

        try {
            $this->section('Cart');
            $this->testCartBasics();

            $this->section('Pricing');
            $this->testPricing();

            $this->section('Coupons');
            $this->testCoupons();

            $this->section('Journey switch');
            $this->testJourney();

            $this->section('Order creation');
            $this->testOrder();

            $this->section('Idempotency');
            $this->testIdempotency();

            $this->section('Stock');
            $this->testStock();
        } catch (Throwable $e) {
            $this->fail++;
            CLI::write('  UNCAUGHT: ' . $e->getMessage(), 'red');
            CLI::write('    at ' . $e->getFile() . ':' . $e->getLine(), 'dark_gray');
        } finally {
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write(
            sprintf('  %d passed, %d failed', $this->pass, $this->fail),
            $this->fail === 0 ? 'green' : 'red'
        );
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ----------------------------------------------------------- helpers

    private function section(string $title): void
    {
        CLI::write('  ' . $title, 'white');
        CLI::write('  ' . str_repeat('-', 54), 'dark_gray');
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->pass++;
            CLI::write(sprintf('  [ ok ] %-34s %s', $label, $detail), 'green');
        } else {
            $this->fail++;
            CLI::write(sprintf('  [FAIL] %-34s %s', $label, $detail), 'red');
        }
    }

    private function cart(): \App\Services\CartService
    {
        return service('cart');
    }

    private function resetCart(): void
    {
        $cart = $this->cart()->current();

        if ($cart !== null) {
            db_connect()->table('cart_items')->where('cart_id', $cart['id'])->delete();
            db_connect()->table('carts')->where('id', $cart['id'])->delete();
        }

        session()->remove('cart_uuid');
    }

    // ------------------------------------------------------------- tests

    private function testCartBasics(): void
    {
        $this->resetCart();

        $chocolate = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72');
        $tea       = model(ProductModel::class)->findVisibleBySlug('masala-chai-blend');

        $this->check('empty cart snapshot', $this->cart()->snapshot()['is_empty'], 'is_empty = true');

        $add = $this->cart()->addProduct($chocolate->id, 2);
        $this->check('add product', $add['ok'], $add['message']);

        $again = $this->cart()->addProduct($chocolate->id, 3);
        $snapshot = $this->cart()->snapshot();
        $this->check(
            'adding same product merges',
            $snapshot['line_count'] === 1 && $snapshot['lines'][0]['quantity'] === 5,
            'lines=' . $snapshot['line_count'] . ' qty=' . $snapshot['lines'][0]['quantity']
        );

        $this->cart()->addProduct($tea->id, 1);
        $snapshot = $this->cart()->snapshot();
        $this->check('second product is a new line', $snapshot['line_count'] === 2, 'lines=' . $snapshot['line_count']);

        $lineId = $snapshot['lines'][1]['line_id'];
        $this->cart()->updateQuantity($lineId, 4);
        $snapshot = $this->cart()->snapshot();
        $this->check('update quantity', $snapshot['lines'][1]['quantity'] === 4, 'qty=' . $snapshot['lines'][1]['quantity']);

        $this->cart()->updateQuantity($lineId, 0);
        $snapshot = $this->cart()->snapshot();
        $this->check('quantity 0 removes the line', $snapshot['line_count'] === 1, 'lines=' . $snapshot['line_count']);

        // A line id from another cart must not be touchable.
        $foreign = $this->cart()->removeLine(999999);
        $this->check('cannot remove a foreign line', ! $foreign['ok'], $foreign['message']);
    }

    private function testPricing(): void
    {
        $this->resetCart();

        $chocolate = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72'); // 320
        $this->cart()->addProduct($chocolate->id, 3);

        $snapshot = $this->cart()->snapshot();
        $expected = 320.0 * 3;

        $this->check(
            'subtotal computed from DB',
            abs($snapshot['subtotal'] - $expected) < 0.01,
            rs_money($snapshot['subtotal']) . ' (expected ' . rs_money($expected) . ')'
        );

        // 960 is below the 1500 free-delivery threshold, so the flat rate applies.
        $this->check(
            'shipping applied below threshold',
            $snapshot['shipping_total'] > 0,
            rs_money($snapshot['shipping_total'])
        );

        $this->check(
            'grand total = subtotal + shipping',
            abs($snapshot['grand_total'] - ($snapshot['subtotal'] + $snapshot['shipping_total'])) < 0.01,
            rs_money($snapshot['grand_total'])
        );

        // Tamper with the snapshot column and confirm pricing ignores it.
        $lineId = $snapshot['lines'][0]['line_id'];
        db_connect()->table('cart_items')->where('id', $lineId)->update([
            'unit_price_snapshot' => 1,
            'line_total_snapshot' => 3,
        ]);

        $after = $this->cart()->snapshot();
        $this->check(
            'tampered snapshot ignored',
            abs($after['subtotal'] - $expected) < 0.01,
            'still ' . rs_money($after['subtotal'])
        );

        // Push over the threshold: delivery should become free.
        $platter = model(ProductModel::class)->findVisibleBySlug('blue-pottery-platter'); // 1650
        $this->cart()->addProduct($platter->id, 1);
        $over = $this->cart()->snapshot();
        $this->check(
            'free delivery above threshold',
            $over['shipping_total'] === 0.0,
            'subtotal ' . rs_money($over['subtotal'])
        );
    }

    private function testCoupons(): void
    {
        $this->resetCart();
        $platter = model(ProductModel::class)->findVisibleBySlug('blue-pottery-platter'); // 1650
        $this->cart()->addProduct($platter->id, 1);

        $bad = $this->cart()->applyCoupon('NOPE-NOT-REAL');
        $this->check('unknown code rejected', ! $bad['ok'], $bad['message']);

        $expired = $this->cart()->applyCoupon('LASTYEAR');
        $this->check('expired code rejected', ! $expired['ok'], $expired['message']);

        $tooSmall = $this->cart()->applyCoupon('DIWALI500');
        $this->check('minimum-order code rejected', ! $tooSmall['ok'], $tooSmall['message']);

        $ok = $this->cart()->applyCoupon('welcome10');   // lowercase on purpose
        $snapshot = $this->cart()->snapshot();
        $this->check(
            'percent code applied (case-insensitive)',
            $ok['ok'] && $snapshot['discount_total'] > 0,
            rs_money($snapshot['discount_total']) . ' off'
        );

        $this->check(
            'percent cap respected',
            $snapshot['discount_total'] <= 300.0,
            rs_money($snapshot['discount_total']) . ' (cap ₹300)'
        );

        $this->cart()->removeCoupon();
        $this->check('coupon removed', $this->cart()->snapshot()['discount_total'] === 0.0, '');

        // Free shipping, on a cart small enough to normally be charged.
        $this->resetCart();
        $bar = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72');
        $this->cart()->addProduct($bar->id, 1);
        $this->cart()->applyCoupon('FREESHIP');
        $ship = $this->cart()->snapshot();
        $this->check('free-shipping code zeroes delivery', $ship['shipping_total'] === 0.0, '');
    }

    private function testJourney(): void
    {
        $this->resetCart();
        $bar = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72');
        $this->cart()->addProduct($bar->id, 1);

        service('settings')->set('journey_mode', 'buy_now');
        service('settings')->flush();
        $this->check('site in Buy mode → buy_now', $this->cart()->snapshot()['journey_mode'] === 'buy_now', '');

        service('settings')->set('journey_mode', 'enquire_now');
        service('settings')->flush();
        $this->check('site in Enquire mode → enquire_now', $this->cart()->snapshot()['journey_mode'] === 'enquire_now', '');

        service('settings')->set('journey_mode', 'buy_now');
        service('settings')->flush();

        // A single pinned item must convert the whole basket to an enquiry.
        db_connect()->table('products')->where('slug', 'blue-pottery-platter')
            ->update(['sale_mode' => 'enquire_now']);

        $platter = model(ProductModel::class)->findVisibleBySlug('blue-pottery-platter');
        $this->cart()->addProduct($platter->id, 1);

        $this->check(
            'one quoted item converts the basket',
            $this->cart()->snapshot()['journey_mode'] === 'enquire_now',
            'site is Buy, basket is Enquire'
        );

        db_connect()->table('products')->where('slug', 'blue-pottery-platter')
            ->update(['sale_mode' => 'inherit']);
    }

    private function testOrder(): void
    {
        $this->resetCart();
        $bar = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72');
        $before = $bar->stock_qty;

        $this->cart()->addProduct($bar->id, 2);
        $this->cart()->applyCoupon('FREESHIP');
        $snapshot = $this->cart()->snapshot();

        $result = service('orders')->placeFromCart($this->customerInput(), 'diag-' . bin2hex(random_bytes(8)));

        $this->check('order placed', $result['ok'], (string) ($result['error'] ?? ''));

        if (! $result['ok']) {
            return;
        }

        $order = $result['order'];
        $this->createdOrders[] = (int) $order['id'];

        $this->check(
            'reference formatted',
            (bool) preg_match('/^RSM-\d{4}-\d{6}$/', $order['order_ref']),
            $order['order_ref']
        );
        $this->check('uuid assigned', strlen((string) $order['uuid']) === 36, $order['uuid']);
        $this->check(
            'total matches the priced cart',
            abs((float) $order['grand_total'] - (float) $snapshot['grand_total']) < 0.01,
            rs_money($order['grand_total'])
        );
        $this->check(
            'payment recorded as unpaid (no gateway)',
            $order['payment_status'] === 'unpaid',
            $order['payment_status']
        );

        $items = model(OrderItemModel::class)->forOrder((int) $order['id']);
        $this->check('line snapshots written', count($items) === 1, count($items) . ' item(s)');
        $this->check(
            'name snapshotted',
            $items[0]['name_snapshot'] === '72% Dark Chocolate',
            $items[0]['name_snapshot']
        );

        $after = model(ProductModel::class)->find($bar->id)->stock_qty;
        $this->check('stock reserved', $after === $before - 2, $before . ' → ' . $after);

        $history = db_connect()->table('order_status_history')
            ->where('order_id', $order['id'])->countAllResults();
        $this->check('status history recorded', $history === 1, $history . ' row');

        $notifications = db_connect()->table('notification_log')
            ->where('related_id', $order['id'])->countAllResults();
        $this->check('notifications queued', $notifications > 0, $notifications . ' queued');

        $redemption = db_connect()->table('coupon_redemptions')
            ->where('order_id', $order['id'])->countAllResults();
        $this->check('coupon redemption logged', $redemption === 1, $redemption . ' row');

        $this->check('cart emptied after order', $this->cart()->snapshot()['is_empty'], '');
    }

    private function testIdempotency(): void
    {
        $this->resetCart();
        $bar = model(ProductModel::class)->findVisibleBySlug('cacao-nib-brittle');
        $this->cart()->addProduct($bar->id, 1);

        $key   = 'diag-idem-' . bin2hex(random_bytes(6));
        $first = service('orders')->placeFromCart($this->customerInput(), $key);

        if ($first['ok']) {
            $this->createdOrders[] = (int) $first['order']['id'];
        }

        $second = service('orders')->placeFromCart($this->customerInput(), $key);

        $this->check('first submit creates an order', $first['ok'], (string) ($first['order']['order_ref'] ?? ''));
        $this->check(
            'same key returns the same order',
            $second['ok']
                && ($second['duplicate'] ?? false)
                && $second['order']['id'] === $first['order']['id'],
            'no duplicate created'
        );

        $count = model(OrderModel::class)->where('idempotency_key', $key)->countAllResults();
        $this->check('exactly one row for that key', $count === 1, $count . ' row');
    }

    private function testStock(): void
    {
        $this->resetCart();

        // Walnut Halves is seeded with stock 0.
        $soldOut = model(ProductModel::class)->findVisibleBySlug('walnut-halves');
        $add     = $this->cart()->addProduct($soldOut->id, 1);
        $this->check('sold-out product refused', ! $add['ok'], $add['message']);

        // Clamp a request for more than exists.
        $limited = model(ProductModel::class)->findVisibleBySlug('blue-pottery-platter');
        $stock   = $limited->stock_qty;

        $this->resetCart();
        $this->cart()->addProduct($limited->id, $stock + 50);
        $snapshot = $this->cart()->snapshot();

        $this->check(
            'quantity clamped to stock',
            $snapshot['lines'] !== [] && $snapshot['lines'][0]['quantity'] <= $stock,
            'asked ' . ($stock + 50) . ', got ' . ($snapshot['lines'][0]['quantity'] ?? 0) . ' (stock ' . $stock . ')'
        );

        $this->resetCart();
    }

    /** @return array<string, mixed> */
    private function customerInput(): array
    {
        return [
            'customer_name'     => 'Diag Tester',
            'customer_email'    => 'diag@example.test',
            'customer_phone'    => '9876543210',
            'ship_name'         => 'Diag Tester',
            'ship_phone'        => '9876543210',
            'ship_line1'        => '1 Test Lane',
            'ship_city'         => 'Jaipur',
            'ship_state'        => 'Rajasthan',
            'ship_postal_code'  => '302001',
            'ship_country'      => 'India',
            'bill_same_as_ship' => true,
            'gift_message'      => 'Happy testing.',
            'customer_note'     => null,
            'spam_score'        => 0,
        ];
    }

    private function cleanup(): void
    {
        $db = db_connect();

        foreach ($this->createdOrders as $orderId) {
            // Put reserved stock back so repeated runs do not drain the catalogue.
            foreach (model(OrderItemModel::class)->forOrder($orderId) as $item) {
                if ($item['product_id'] !== null) {
                    $db->query(
                        'UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?',
                        [(int) $item['quantity'], (int) $item['product_id']]
                    );
                }
            }

            $db->table('notification_log')->where('related_id', $orderId)->delete();
            $db->table('coupon_redemptions')->where('order_id', $orderId)->delete();
            $db->table('orders')->where('id', $orderId)->delete();
        }

        $db->query('UPDATE coupons SET used_count = 0 WHERE code IN (?, ?, ?)', ['WELCOME10', 'DIWALI500', 'FREESHIP']);
        $this->resetCart();

        if ($this->createdOrders !== []) {
            CLI::newLine();
            CLI::write('  Cleaned up ' . count($this->createdOrders) . ' test order(s).', 'dark_gray');
        }
    }
}
