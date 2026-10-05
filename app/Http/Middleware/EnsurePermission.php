<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fail-closed wrapper around Spatie's permission middleware.
 *
 * - Keeps the existing `permission:a|b` syntax and semantics intact, so every
 *   already-protected route gains these guarantees with zero route changes.
 * - Missing permission, unknown permission name, cache/DB failure or ANY
 *   unexpected error => HTTP 403 (never fails open).
 * - Denied attempts are audit-logged (without sensitive data).
 */
class EnsurePermission extends PermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $guard = null)
    {
        try {
            return parent::handle($request, $next, $permission, $guard);
        } catch (UnauthorizedException $e) {
            $this->audit($request, $permission);

            throw $e;
        } catch (Throwable $e) {
            // Fail closed: any error while determining authorization is a deny.
            $this->audit($request, $permission, $e::class);

            Log::warning('permission.middleware_error', [
                'permission' => $permission,
                'error' => $e::class,
                'path' => $request->method().' '.$request->path(),
            ]);

            throw UnauthorizedException::forPermissions(explode('|', $permission));
        }
    }

    protected function audit($request, string $permission, string $reason = 'denied'): void
    {
        $user = auth()->user();

        if (! $user) {
            return; // Unauthenticated => 401 path, nothing to audit here.
        }

        try {
            \App\Services\AuditLogService::log('permission.denied', null, [], [
                'permission' => $permission,
                'reason' => $reason,
                'path' => $request->method().' '.$request->path(),
            ]);
        } catch (Throwable) {
            // Audit failures must not affect the (still denied) response.
        }
    }
}
