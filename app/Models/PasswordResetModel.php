<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Password-reset tokens.
 *
 * Only the SHA-256 of the emailed token is stored. A leaked database therefore
 * does not let anyone reset an account — the same reasoning as never storing a
 * password itself. Tokens are single-use and short-lived.
 */
class PasswordResetModel extends Model
{
    protected $table         = 'password_resets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'user_type', 'user_id', 'email', 'token_hash',
        'expires_at', 'used_at', 'requested_ip',
    ];

    /** How long a link stays usable. Long enough to find the email, no longer. */
    public const TTL_MINUTES = 60;

    /**
     * Issue a token. Returns the PLAIN token for emailing — it is never
     * persisted and cannot be recovered afterwards.
     */
    public function issue(string $userType, int $userId, string $email, string $ip): string
    {
        // Any earlier outstanding token becomes void: requesting a new link
        // should invalidate the old one.
        $this->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('used_at', null)
            ->set('used_at', date('Y-m-d H:i:s'))
            ->update();

        $token = bin2hex(random_bytes(32));

        $this->insert([
            'user_type'    => $userType,
            'user_id'      => $userId,
            'email'        => $email,
            'token_hash'   => hash('sha256', $token),
            'expires_at'   => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
            'requested_ip' => $ip,
        ]);

        return $token;
    }

    /**
     * Look up an unused, unexpired token.
     *
     * @return array<string, mixed>|null
     */
    public function consume(string $token, string $userType = 'customer'): ?array
    {
        if (trim($token) === '') {
            return null;
        }

        $row = $this->where('token_hash', hash('sha256', $token))
            ->where('user_type', $userType)
            ->where('used_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();

        if ($row === null) {
            return null;
        }

        // Burn it before the caller does anything else, so a double submit
        // cannot reset twice.
        $this->update($row['id'], ['used_at' => date('Y-m-d H:i:s')]);

        return $row;
    }
}
