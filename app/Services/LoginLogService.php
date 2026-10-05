<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists authentication attempts for the admin access log.
 *
 * Security notes:
 *  - never throws: audit persistence must not break (or leak details of) login
 *  - stores only the typed identifier, status, reason, ip and user agent
 *  - passwords / tokens are never written here
 */
class LoginLogService
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const LOCKED = 'locked';
    public const INACTIVE = 'inactive';

    public static function record(
        string $status,
        string $identifier,
        ?User $user = null,
        ?string $reason = null,
        ?Request $request = null,
        string $driver = 'password',
    ): void {
        $request ??= request();

        try {
            LoginLog::create([
                'user_id' => $user?->id,
                'login_identifier' => mb_substr(trim((string) $identifier), 0, 190),
                'status' => $status,
                'reason' => $reason,
                'driver' => $driver,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (Throwable $e) {
            Log::warning('login_log write failed: ' . $e->getMessage());
        }
    }
}
