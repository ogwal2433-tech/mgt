<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ?AuditLog {
        $user = Auth::user();

        try {
            return AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->username ?? $user?->email,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => $oldValues ? self::sanitize($oldValues) : null,
                'new_values' => $newValues ? self::sanitize($newValues) : null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
                'request_id' => Request::header('X-Request-Id'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function sanitize(array $values): array
    {
        $blocked = ['password', 'password_confirmation', 'two_factor_secret', 'remember_token'];

        foreach ($blocked as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }
}
