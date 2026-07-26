<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Preflight and smoke test.
 *
 *   php spark rasmein:diag
 *
 * Checks the environment first (PHP version, extensions, .env, writable paths,
 * database connection), then runs every storefront query. Run it after any
 * install or deploy — it turns "the site is broken" into a specific line.
 *
 * CLI only. Not reachable over HTTP.
 */
class Diag extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag';
    protected $description = 'Check the environment and smoke-test every storefront query.';

    private int $failures = 0;

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write('Rasmein — environment check', 'white');
        CLI::write(str_repeat('-', 58), 'dark_gray');

        $this->checkPhp();
        $this->checkExtensions();
        $this->checkEnvironment();
        $this->checkWritablePaths();

        $dbUp = $this->checkDatabase();

        if ($dbUp) {
            CLI::newLine();
            CLI::write('Storefront queries', 'white');
            CLI::write(str_repeat('-', 58), 'dark_gray');
            $this->checkQueries();
        }

        CLI::newLine();

        if ($this->failures === 0) {
            CLI::write('  All checks passed.', 'green');
        } else {
            CLI::write(sprintf('  %d check(s) failed — see the notes above.', $this->failures), 'red');
        }

        CLI::newLine();

        return $this->failures === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ------------------------------------------------------------ reporting

    private function pass(string $label, string $detail = ''): void
    {
        CLI::write(sprintf('  [ ok ] %-30s %s', $label, $detail), 'green');
    }

    private function fail(string $label, string $detail, string $fix = ''): void
    {
        $this->failures++;
        CLI::write(sprintf('  [FAIL] %-30s %s', $label, $detail), 'red');

        if ($fix !== '') {
            CLI::write('         → ' . $fix, 'yellow');
        }
    }

    private function warn(string $label, string $detail, string $fix = ''): void
    {
        CLI::write(sprintf('  [warn] %-30s %s', $label, $detail), 'yellow');

        if ($fix !== '') {
            CLI::write('         → ' . $fix, 'dark_gray');
        }
    }

    // -------------------------------------------------------------- checks

    private function checkPhp(): void
    {
        // CodeIgniter 4.7 requires 8.2. XAMPP often ships an older build, and
        // the resulting failures are obscure, so this is checked first.
        if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
            $this->pass('PHP version', PHP_VERSION);

            return;
        }

        $this->fail(
            'PHP version',
            PHP_VERSION . ' — CodeIgniter 4.7 needs 8.2 or newer',
            'On XAMPP, install a build with PHP 8.2+, or point your CLI at a newer php.exe.'
        );
    }

    private function checkExtensions(): void
    {
        $required = [
            'intl'      => 'Required by CodeIgniter itself, and by Indian-format currency output.',
            'mbstring'  => 'Required by CodeIgniter for multibyte string handling.',
            'json'      => 'Required for settings and API responses.',
            'mysqli'    => 'Required to reach MySQL/MariaDB.',
            'fileinfo'  => 'Needed to verify uploaded image types by content, not extension.',
        ];

        $optional = [
            'curl'    => 'Needed later for the payment gateway and SMS provider.',
            'gd'      => 'Needed later for product image resizing.',
            'openssl' => 'Needed for SMTP over TLS.',
            'zip'     => 'Used by Composer and by data exports.',
        ];

        foreach ($required as $extension => $why) {
            if (extension_loaded($extension)) {
                $this->pass('ext-' . $extension, 'loaded');
                continue;
            }

            $this->fail(
                'ext-' . $extension,
                'MISSING — ' . $why,
                'Open php.ini, remove the ";" before "extension=' . $extension . '", then restart Apache and your terminal.'
            );
        }

        foreach ($optional as $extension => $why) {
            extension_loaded($extension)
                ? $this->pass('ext-' . $extension, 'loaded')
                : $this->warn('ext-' . $extension, 'not loaded — ' . $why, 'Enable it in php.ini before that phase.');
        }
    }

    private function checkEnvironment(): void
    {
        $envFile = ROOTPATH . '.env';

        if (! is_file($envFile)) {
            $this->fail(
                '.env file',
                'not found at project root',
                'Copy .env.example to .env, then run: php spark key:generate'
            );
        } elseif (! is_writable($envFile)) {
            $this->warn('.env file', 'present but not writable', 'key:generate needs to write to it.');
        } else {
            $this->pass('.env file', 'present and writable');
        }

        $this->pass('CI_ENVIRONMENT', ENVIRONMENT);

        $key = env('encryption.key', '');

        if ($key === '' || $key === null) {
            $this->fail(
                'encryption.key',
                'not set',
                'Run: php spark key:generate'
            );
        } else {
            $this->pass('encryption.key', 'set (' . strlen((string) $key) . ' chars)');
        }

        $baseUrl = (string) config('App')->baseURL;

        if ($baseUrl === '' || $baseUrl === 'http://localhost:8080/') {
            $this->warn(
                'app.baseURL',
                $baseUrl === '' ? 'empty' : $baseUrl,
                'Set it in .env to the URL you actually browse, e.g. http://localhost/rasmein/public/'
            );
        } else {
            $this->pass('app.baseURL', $baseUrl);
        }

        $css = FCPATH . 'assets/css/app.css';

        is_file($css)
            ? $this->pass('compiled CSS', number_format((float) filesize($css) / 1024, 1) . ' KB')
            : $this->fail('compiled CSS', 'public/assets/css/app.css missing', 'Run: npm install && npm run build');
    }

    /**
     * Compare migration files on disk with the migrations table.
     *
     * Pulling new code without running `spark migrate` leaves the application
     * expecting columns the database does not have. The symptom is an
     * "Undefined array key" deep in a service, which tells you nothing. This
     * turns it into one line naming the command to run.
     */
    private function checkMigrations(): void
    {
        $directory = APPPATH . 'Database/Migrations';

        if (! is_dir($directory)) {
            return;
        }

        $onDisk = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2}-\d{6})_/', basename($file), $m) === 1) {
                $onDisk[] = $m[1];
            }
        }

        if ($onDisk === []) {
            return;
        }

        try {
            $applied = array_column(
                db_connect()->table('migrations')->select('version')->get()->getResultArray(),
                'version'
            );
        } catch (Throwable) {
            $this->fail('migrations', 'the migrations table is missing', 'Run: php spark migrate');

            return;
        }

        $pending = array_values(array_diff($onDisk, $applied));

        if ($pending === []) {
            $this->pass('migrations', count($onDisk) . ' applied, none pending');

            return;
        }

        $this->fail(
            'migrations',
            count($pending) . ' pending — the code expects columns your database does not have',
            'Run: php spark migrate   (pending: ' . implode(', ', $pending) . ')'
        );
    }

    private function checkWritablePaths(): void
    {
        foreach (['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads'] as $relative) {
            $path = ROOTPATH . $relative;

            if (! is_dir($path)) {
                $this->fail($relative . '/', 'missing', 'Recreate the directory.');
                continue;
            }

            is_writable($path)
                ? $this->pass($relative . '/', 'writable')
                : $this->fail($relative . '/', 'not writable', 'Grant the web server write access (775 on Linux).');
        }
    }

    /**
     * Connect with mysqli directly before letting CodeIgniter try.
     *
     * CI4 reports every connection problem as the same "Unable to connect to
     * the database." — the real reason (wrong password vs. missing database vs.
     * server not running) only reaches writable/logs. This probe reads the
     * driver's own error and prints it, which is the difference between a
     * five-second fix and an afternoon.
     */
    private function probeMysql(array $config): bool
    {
        if (! extension_loaded('mysqli')) {
            return true; // already reported by checkExtensions()
        }

        $host = (string) ($config['hostname'] ?: 'localhost');
        $port = (int) ($config['port'] ?: 3306);
        $user = (string) $config['username'];
        $name = (string) $config['database'];
        $pass = (string) $config['password'];

        mysqli_report(MYSQLI_REPORT_OFF);

        // Step 1 — reach the server at all, without naming a database.
        $conn = @new \mysqli($host, $user, $pass, '', $port);

        if ($conn->connect_errno !== 0) {
            $reason = $conn->connect_error;

            $fix = match (true) {
                str_contains($reason, 'Access denied') => 'Wrong username or password. XAMPP\'s default is username "root" with an EMPTY password: set database.default.username = root and database.default.password = \'\' in .env',
                str_contains($reason, 'No such file')
                || str_contains($reason, 'Connection refused')
                || str_contains($reason, "Can't connect") => 'MySQL is not reachable on ' . $host . ':' . $port . '. Start MySQL in the XAMPP Control Panel, and check the port there — XAMPP sometimes uses 3307 if 3306 was taken.',
                default => 'Check database.default.hostname / port / username / password in .env. On Windows, try 127.0.0.1 instead of localhost.',
            };

            $this->fail('MySQL server', $reason, $fix);

            return false;
        }

        $this->pass('MySQL server', $user . '@' . $host . ':' . $port . ' (' . $conn->server_info . ')');

        // Step 2 — does the database exist, and can this user see it?
        $result = $conn->query('SHOW DATABASES LIKE ' . "'" . $conn->real_escape_string($name) . "'");
        $exists = $result !== false && $result->num_rows > 0;

        if (! $exists) {
            $this->fail(
                'database "' . $name . '"',
                'does not exist, or this user cannot see it',
                'Create it — in phpMyAdmin, or: mysql -u root -e "CREATE DATABASE '
                . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
            );
            $conn->close();

            return false;
        }

        $this->pass('database "' . $name . '"', 'exists');
        $conn->close();

        return true;
    }

    private function checkDatabase(): bool
    {
        $config = config(Database::class)->default;

        if (! $this->probeMysql($config)) {
            return false;
        }

        try {
            $db = \Config\Database::connect();
            $db->initialize();

            $version = $db->getVersion();
            $this->pass('database connection', $config['database'] . ' @ ' . $config['hostname'] . ' (' . $version . ')');
        } catch (Throwable $e) {
            $this->fail(
                'database connection',
                $e->getMessage(),
                'Check database.default.* in .env, and that MySQL is running in the XAMPP control panel.'
            );

            return false;
        }

        try {
            $tables = count($db->listTables());

            if ($tables === 0) {
                $this->fail('schema', 'no tables found', 'Run: php spark migrate');

                return false;
            }

            $this->pass('schema', $tables . ' tables');
            $this->checkMigrations();

            $products = $db->table('products')->countAllResults();
            $settings = $db->table('settings')->countAllResults();

            if ($settings === 0) {
                $this->fail('seed data', 'settings table is empty', 'Run: php spark db:seed DatabaseSeeder');

                return false;
            }

            $this->pass('seed data', $products . ' products, ' . $settings . ' settings');

            // A migrated-but-unseeded templates table means no email can be
            // sent, and the admin list looks simply empty. Say so.
            $templates = $db->table('email_templates')->countAllResults();

            if ($templates === 0) {
                $this->fail(
                    'email templates',
                    'none installed — no email can be sent',
                    'Run: php spark db:seed EmailTemplateSeeder   (or use Admin → Email templates → Install missing)'
                );
            } else {
                $this->pass('email templates', $templates . ' installed');

            // A baseURL that does not match the host the admin browses on makes
            // fetch() cross-origin: cookies are withheld, CSRF fails, and the
            // editor's image upload returns a 403 that looks like a permissions
            // problem. Worth flagging here rather than discovering it that way.
            $base = rtrim((string) config(\Config\App::class)->baseURL, '/');

            if ($base === '') {
                $this->fail('baseURL sanity', 'app.baseURL is empty', 'Set app.baseURL in .env to the exact address you use, including the port.');
            } elseif (ENVIRONMENT === 'production' && (str_contains($base, 'localhost') || str_contains($base, '127.0.0.1'))) {
                $this->fail('baseURL sanity', 'production baseURL still points at localhost: ' . $base, 'Set app.baseURL in .env to the live address.');
            } else {
                $this->pass('baseURL sanity', $base);
            }
            }
        } catch (Throwable $e) {
            $this->fail('schema', $e->getMessage(), 'Run: php spark migrate');

            return false;
        }

        return true;
    }

    private function checkQueries(): void
    {
        $checks = [
            'categories.activeTopLevel' => static fn (): int => count(model(\App\Models\CategoryModel::class)->activeTopLevel()),
            'categories.withCounts'     => static fn (): int => count(model(\App\Models\CategoryModel::class)->withProductCounts(true, 6)),
            'products.featured'         => static fn (): int => count(model(\App\Models\ProductModel::class)->featured(8)),
            'products.latest'           => static fn (): int => count(model(\App\Models\ProductModel::class)->latest(4)),
            'products.giftBoxEligible'  => static fn (): int => count(model(\App\Models\ProductModel::class)->giftBoxEligible(6)),
            'giftboxes.featured'        => static fn (): int => count(model(\App\Models\GiftBoxModel::class)->featured(3)),
            'giftboxes.allowedProducts' => static fn (): int => count(model(\App\Models\GiftBoxModel::class)->allowedProductIds(1)),
            'collections.featured'      => static fn (): int => count(model(\App\Models\CollectionModel::class)->featured(3)),
            'banners.hero'              => static fn (): int => count(model(\App\Models\BannerModel::class)->liveFor('home_hero', 1)),
            'pages.footerLinks'         => static fn (): int => count(model(\App\Models\PageModel::class)->footerLinks()),
        ];

        foreach ($checks as $label => $check) {
            try {
                $this->pass($label, (string) $check() . ' rows');
            } catch (Throwable $e) {
                $this->fail($label, $e->getMessage());
            }
        }

        try {
            $this->pass('settings.journeyMode', service('settings')->journeyMode());
        } catch (Throwable $e) {
            $this->fail('settings.journeyMode', $e->getMessage());
        }
    }
}
