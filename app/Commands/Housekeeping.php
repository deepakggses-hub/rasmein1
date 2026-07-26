<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Scheduled upkeep.
 *
 *   php spark rasmein:housekeeping
 *
 * Daily on cron:
 *   30 2 * * * cd /path/to/rasmein && php spark rasmein:housekeeping >/dev/null 2>&1
 *
 * Raises low-stock notifications, marks stale carts abandoned, and prunes the
 * tables that would otherwise grow forever.
 */
class Housekeeping extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:housekeeping';
    protected $description = 'Low-stock alerts, abandoned carts, and pruning old rows.';

    public function run(array $params)
    {
        $db = db_connect();
        CLI::newLine();

        // --------------------------------------------------- low stock
        $raised = service('notify')->lowStockSweep();
        CLI::write('  low-stock notifications raised: ' . $raised, 'green');

        // ---------------------------------------------- abandoned carts
        // A cart untouched for a week is not coming back. Marking it rather
        // than deleting keeps abandonment reportable.
        $db->table('carts')
            ->where('status', 'active')
            ->where('last_activity_at <', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->update(['status' => 'abandoned']);
        CLI::write('  carts marked abandoned: ' . $db->affectedRows(), 'green');

        // Abandoned for 90 days: delete. Components and items go with them via
        // the foreign keys.
        $db->table('carts')
            ->where('status', 'abandoned')
            ->where('updated_at <', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->delete();
        CLI::write('  old carts deleted: ' . $db->affectedRows(), 'green');

        // ------------------------------------------- expired reset tokens
        $db->table('password_resets')
            ->where('expires_at <', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->delete();
        CLI::write('  expired reset tokens deleted: ' . $db->affectedRows(), 'green');

        // ----------------------------------------------- old sent mail
        // Keep failures: someone may need to see why. Prune successes.
        $db->table('notification_log')
            ->where('status', 'sent')
            ->where('sent_at <', date('Y-m-d H:i:s', strtotime('-180 days')))
            ->delete();
        CLI::write('  old sent mail rows pruned: ' . $db->affectedRows(), 'green');

        // ------------------------------------------- read notifications
        $db->table('admin_notifications')
            ->where('is_read', 1)
            ->where('read_at <', date('Y-m-d H:i:s', strtotime('-60 days')))
            ->delete();
        CLI::write('  read notifications pruned: ' . $db->affectedRows(), 'green');

        // --------------------------------------------- stale login rows
        $db->table('auth_login_attempts')
            ->where('created_at <', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->delete();
        CLI::write('  old login attempts pruned: ' . $db->affectedRows(), 'green');

        CLI::newLine();
        CLI::write('  Housekeeping done.', 'green');
        CLI::newLine();

        return EXIT_SUCCESS;
    }
}
