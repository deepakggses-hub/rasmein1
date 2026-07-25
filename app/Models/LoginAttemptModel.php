<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * A durable record of sign-in attempts.
 *
 * Throttling itself uses CI4's Throttler (cache-backed, fast). This table is
 * the audit trail: it answers "was this account under attack last Tuesday?"
 * after the cache has long expired.
 */
class LoginAttemptModel extends Model
{
    protected $table         = 'auth_login_attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'user_type', 'identifier', 'was_successful', 'ip_address', 'user_agent',
    ];

    public function record(string $userType, string $identifier, bool $successful): void
    {
        $request = service('request');

        $this->insert([
            'user_type'      => $userType,
            'identifier'     => mb_substr($identifier, 0, 191),
            'was_successful' => $successful ? 1 : 0,
            'ip_address'     => $request->getIPAddress(),
            'user_agent'     => rs_user_agent(),
        ]);
    }

    public function recentFailures(string $identifier, int $withinMinutes = 15): int
    {
        return $this->where('identifier', $identifier)
            ->where('was_successful', 0)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - ($withinMinutes * 60)))
            ->countAllResults();
    }
}
