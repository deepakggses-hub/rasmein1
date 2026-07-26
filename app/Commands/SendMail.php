<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Drains the outbound email queue.
 *
 *   php spark rasmein:send-mail
 *
 * Put this on cron every five minutes. The crontab line is in
 * docs/DEPLOYMENT.md — a cron expression cannot be written inside a PHP
 * docblock, because its slash-star sequence closes the comment.
 *
 * Sending is queued rather than inline so a slow mail server never delays a
 * checkout, and a failure never rolls back an order.
 */
class SendMail extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:send-mail';
    protected $description = 'Send queued emails, with capped retries and backoff.';
    protected $usage       = 'rasmein:send-mail [--limit=50]';

    public function run(array $params)
    {
        $limit = (int) ($params['limit'] ?? CLI::getOption('limit') ?? 50);
        $limit = max(1, min(500, $limit));

        $result = service('mail')->drainQueue($limit);

        $line = sprintf(
            '  sent %d · retrying %d · failed %d',
            $result['sent'],
            $result['skipped'],
            $result['failed']
        );

        CLI::write($line, $result['failed'] > 0 ? 'yellow' : 'green');

        if ($result['failed'] > 0) {
            CLI::write('  Failed messages have exhausted their retries — see writable/logs.', 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
