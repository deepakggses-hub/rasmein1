<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AdminUserModel;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * The path a brand-new install actually takes.
 *
 *   php spark rasmein:diag-firstrun --fresh
 *
 * Three bugs reached the client because the other suites exercise services
 * directly and never walk a clean install: a missing migration column, an admin
 * screen that had never been rendered, and an edit form whose validation had
 * never been run. This walks it: migrate from zero, seed, then touch every
 * first-run invariant in order.
 *
 * --fresh DROPS AND REBUILDS the database. Development only.
 */
class DiagFirstRun extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-firstrun';
    protected $description = 'Walk a clean install: migrate, seed, and check every first-run invariant.';
    protected $usage       = 'rasmein:diag-firstrun [--fresh]';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('  Refusing to run in production.');

            return EXIT_ERROR;
        }

        CLI::newLine();

        if (array_key_exists('fresh', $params) || in_array('--fresh', $_SERVER['argv'] ?? [], true)) {
            CLI::write('  Rebuilding the database from zero…', 'yellow');
            command('migrate:rollback -b 0');
            command('migrate');
            command('db:seed DatabaseSeeder');
            CLI::newLine();
        }

        $this->section('Schema and code agree');
        $this->checkMigrations();

        $this->section('Seeded state');
        $this->checkSeed();

        $this->section('Storefront renders from seed data');
        $this->checkStorefront();

        $this->section('A first order can be placed');
        $this->checkOrderCycle();

        CLI::newLine();
        CLI::write(sprintf('  %d passed, %d failed', $this->pass, $this->fail), $this->fail === 0 ? 'green' : 'red');
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function section(string $title): void
    {
        CLI::write('  ' . $title, 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->pass++ : $this->fail++;
        CLI::write(sprintf('  [%s] %-36s %s', $ok ? ' ok ' : 'FAIL', $label, $detail), $ok ? 'green' : 'red');
    }

    /** The class of bug that produced the coupon_code crash. */
    private function checkMigrations(): void
    {
        $onDisk = [];

        foreach (glob(APPPATH . 'Database/Migrations/*.php') ?: [] as $file) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2}-\d{6})_/', basename($file), $m) === 1) {
                $onDisk[] = $m[1];
            }
        }

        $applied = array_column(
            db_connect()->table('migrations')->select('version')->get()->getResultArray(),
            'version'
        );

        $pending = array_diff($onDisk, $applied);

        $this->check('no pending migrations', $pending === [], count($onDisk) . ' on disk, ' . count($applied) . ' applied');

        // Columns the code reads that a stale database would not have.
        $expected = [
            'carts'    => ['coupon_code'],
            'orders'   => ['idempotency_key', 'journey_mode', 'uuid'],
            'products' => ['giftbox_slots', 'sale_mode'],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $this->check(
                    $table . '.' . $column . ' exists',
                    db_connect()->fieldExists($column, $table)
                );
            }
        }
    }

    private function checkSeed(): void
    {
        $db = db_connect();

        foreach (['settings' => 20, 'products' => 1, 'categories' => 1, 'gift_boxes' => 1, 'pages' => 1, 'admin_roles' => 3] as $table => $minimum) {
            $count = $db->table($table)->countAllResults();
            $this->check($table . ' seeded', $count >= $minimum, $count . ' rows');
        }

        $admin = model(AdminUserModel::class)->findActiveByEmail('admin@rasmein.com');
        $this->check('admin account exists', $admin !== null, $admin['email'] ?? '');

        // The forced-password-change screen is the first page a new admin sees.
        // It is also the one that shipped broken.
        $this->check(
            'admin is forced to change password',
            $admin !== null && (int) $admin['must_change_password'] === 1
        );

        $this->check(
            'admin password is hashed, not stored',
            $admin !== null && str_starts_with((string) $admin['password_hash'], '$2y$')
        );

        $withRole = $admin !== null ? model(AdminUserModel::class)->withRole((int) $admin['id']) : null;
        $this->check(
            'admin resolves to a role with permissions',
            $withRole !== null && $withRole['permissions'] !== [],
            $withRole['role_name'] ?? ''
        );

        $this->check(
            'journey mode defaults to Buy',
            service('settings')->journeyMode() === 'buy_now'
        );

        // A category without a path has no URL. On a fresh install this was
        // every one of them, because the seeder does not go through the admin
        // controller that computes it.
        $pathless = $db->table('categories')
            ->groupStart()->where('path', null)->orWhere('path', '')->groupEnd()
            ->countAllResults();

        $this->check(
            'every category has a URL path',
            $pathless === 0,
            $pathless === 0 ? 'all reachable' : $pathless . ' with no path'
        );

        // Root URLs come from two places now. Both must survive a fresh seed —
        // a migration backfill cannot fix rows the seeder inserts afterwards.
        $occasions = $db->table('collections')->where('type', 'occasion')->countAllResults();

        $this->check(
            'occasions seeded with a root URL',
            $occasions > 0,
            $occasions . ' occasion(s)'
        );

        $rootUrls = service('rootUrls');
        $sample   = $db->table('collections')->where('type', 'occasion')->get(1)->getRowArray();

        if ($sample !== null) {
            $this->check(
                'an occasion resolves at the site root',
                $rootUrls->resolve((string) $sample['slug']) !== null,
                '/' . $sample['slug']
            );
        }

        // The two namespaces must not overlap.
        $overlap = (int) ($db->query(
            'SELECT COUNT(*) AS n FROM collections c
             JOIN categories cat ON cat.path = c.slug AND cat.parent_id IS NULL
             WHERE c.type = "occasion"'
        )->getRowArray()['n'] ?? 0);

        $this->check('no category and occasion share a URL', $overlap === 0);
    }

    private function checkStorefront(): void
    {
        $checks = [
            'homepage: featured products' => static fn (): int => count(model(ProductModel::class)->featured(8)),
            'homepage: categories'        => static fn (): int => count(model(\App\Models\CategoryModel::class)->withProductCounts(true, 6)),
            'homepage: gift boxes'        => static fn (): int => count(model(\App\Models\GiftBoxModel::class)->featured(3)),
            'shop: filters + paginate'    => static fn (): int => count(model(ProductModel::class)->applyFilters([])->applySort('price_asc')->findAll(12)),
            'search works'                => static fn (): int => count(model(ProductModel::class)->applyFilters(['q' => 'chocolate'])->findAll(10)),
            'CMS pages in the footer'     => static fn (): int => count(model(\App\Models\PageModel::class)->footerLinks()),
        ];

        foreach ($checks as $label => $check) {
            try {
                $n = $check();
                $this->check($label, $n > 0, $n . ' rows');
            } catch (Throwable $e) {
                $this->check($label, false, $e->getMessage());
            }
        }
    }

    private function checkOrderCycle(): void
    {
        try {
            $product = model(ProductModel::class)
                ->scopeVisible()->where('products.stock_qty >', 5)->first();

            if ($product === null) {
                $this->check('a sellable product exists', false);

                return;
            }

            service('cart')->addProduct($product->id, 1);
            $snapshot = service('cart')->snapshot();
            $this->check('add to cart', ! $snapshot['is_empty'], $product->name);
            $this->check('cart prices from the database', $snapshot['subtotal'] > 0, rs_money($snapshot['subtotal']));

            $result = service('orders')->placeFromCart([
                'customer_name' => 'First Run', 'customer_email' => 'firstrun@example.test',
                'customer_phone' => '9876543210', 'ship_name' => 'First Run',
                'ship_phone' => '9876543210', 'ship_line1' => '1 Test Road',
                'ship_city' => 'Jaipur', 'ship_state' => 'Rajasthan',
                'ship_postal_code' => '302001', 'ship_country' => 'India',
                'bill_same_as_ship' => true, 'spam_score' => 0,
            ], 'firstrun-' . bin2hex(random_bytes(6)));

            $this->check('order placed', $result['ok'], (string) ($result['error'] ?? ''));

            if ($result['ok']) {
                $this->check(
                    'reference formatted',
                    (bool) preg_match('/^RSM-\d{4}-\d{6}$/', $result['order']['order_ref']),
                    $result['order']['order_ref']
                );

                // Leave nothing behind.
                $db = db_connect();

                foreach (model(\App\Models\OrderItemModel::class)->forOrder((int) $result['order']['id']) as $item) {
                    if ($item['product_id'] !== null) {
                        $db->query('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [
                            (int) $item['quantity'], (int) $item['product_id'],
                        ]);
                    }
                }

                $db->table('notification_log')->where('related_id', $result['order']['id'])->delete();
                $db->table('orders')->where('id', $result['order']['id'])->delete();
                CLI::write('         (test order cleaned up)', 'dark_gray');
            }
        } catch (Throwable $e) {
            $this->check('order cycle', false, $e->getMessage());
        }
    }
}
