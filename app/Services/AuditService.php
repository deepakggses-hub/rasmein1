<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAuditLogModel;

/**
 * Records who changed what, when, and from where.
 *
 * Every admin write should call this. It is deliberately forgiving — an audit
 * failure must never break the action being audited — but it always logs the
 * failure so a silent gap in the trail is detectable.
 */
class AuditService
{
    /** Field names that must never reach the audit table. */
    private const REDACTED = [
        'password', 'password_hash', 'password_confirm', 'two_factor_secret',
        'token', 'token_hash', 'api_key', 'secret', 'key_secret',
        'razorpay_key_secret', 'webhook_secret', 'card', 'cvv',
    ];

    public function log(
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $summary = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        try {
            $request = service('request');

            model(AdminAuditLogModel::class)->insert([
                'admin_user_id' => session('admin_id'),
                'action'        => $action,
                'module'        => $module,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'summary'       => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'old_values'    => $this->encode($oldValues),
                'new_values'    => $this->encode($newValues),
                'ip_address'    => $request->getIPAddress(),
                'user_agent'    => rs_user_agent(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Audit write failed for {action}/{module}: {msg}', [
                'action' => $action,
                'module' => $module,
                'msg'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convenience wrapper that records only the fields that actually changed —
     * a diff is far more readable than two full row dumps.
     */
    public function logChange(
        string $module,
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        ?string $summary = null
    ): void {
        $changedBefore = [];
        $changedAfter  = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            if ((string) $previous !== (string) $value) {
                $changedBefore[$field] = $previous;
                $changedAfter[$field]  = $value;
            }
        }

        if ($changedAfter === []) {
            return;
        }

        $this->log('updated', $module, $entityType, $entityId, $summary, $changedBefore, $changedAfter);
    }

    private function encode(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        foreach ($values as $key => $value) {
            foreach (self::REDACTED as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $values[$key] = '[redacted]';
                    continue 2;
                }
            }
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
