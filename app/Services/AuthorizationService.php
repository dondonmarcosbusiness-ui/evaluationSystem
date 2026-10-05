<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Central authorization layer.
 *
 * Isolation principle: business logic asks THIS service one question —
 * "Can this user perform this action?" — and never knows how roles or
 * permissions are stored. The service is FAIL CLOSED: if permission data
 * cannot be loaded or an error occurs, access is DENIED.
 *
 * Default deny: an unassigned or unknown permission always resolves to false.
 */
class AuthorizationService
{
    /**
     * Invalidate Spatie's permission cache after ANY role/permission mutation.
     *
     * Relation-level changes (`$role->permissions()->sync()`, `syncRoles()`,
     * `syncPermissions()`, deletes) do NOT fire Spatie's own cache flush, so
     * the `permission:` middleware would keep enforcing the previous grants
     * until the cache expires (role grants silently "don't take effect").
     * Call this immediately after every mutation.
     */
    public static function flushCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Determine whether the user holds the given capability permission.
     * Fail closed: any failure resolves to false (deny) and is logged.
     */
    public function can(User $user, string $permission): bool
    {
        try {
            // checkPermissionTo() returns false for unknown permissions
            // (default deny) instead of throwing.
            return (bool) $user->checkPermissionTo($permission);
        } catch (Throwable $e) {
            $this->auditDenial($user, $permission, 'permission_lookup_failed: '.$e::class);

            return false;
        }
    }

    /**
     * True when the user holds ANY of the given permissions.
     */
    public function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the data-access scope for a base permission.
     *
     * Resolution order: .all > .team > .own, and a bare base permission
     * without any scope resolves to "own" — possessing `evaluation.view`
     * never implies access to every record.
     *
     * Returns "none" when the user lacks the base permission entirely;
     * callers MUST treat "none" as 403.
     *
     * team scope = same department (faculty.department).
     */
    public function scope(User $user, string $base): string
    {
        if (! $this->can($user, $base)) {
            return 'none';
        }

        try {
            if ($this->can($user, $base.'.all')) {
                return 'all';
            }

            if ($this->can($user, $base.'.team')) {
                return 'team';
            }
        } catch (Throwable $e) {
            $this->auditDenial($user, $base, 'scope_lookup_failed: '.$e::class);

            return 'none';
        }

        return 'own';
    }

    /**
     * Audit a denied attempt and abort with HTTP 403 (fail closed).
     * Used by controllers for in-handler authorization decisions.
     */
    public function denyAndAbort(User $user, string $permission, string $reason = ''): never
    {
        $this->auditDenial($user, $permission, $reason !== '' ? $reason : 'denied');

        abort(403, 'This action is unauthorized.');
    }

    /**
     * Never expose sensitive permission internals to clients.
     */
    protected function auditDenial(User $user, string $permission, string $reason): void
    {
        try {
            AuditLogService::log('permission.denied', null, [], [
                'permission' => $permission,
                'reason' => $reason,
                'path' => request()->method().' '.request()->path(),
            ]);
        } catch (Throwable) {
            // Auditing must never break the request itself; the denial
            // (403) still stands regardless.
        }

        Log::warning('permission.denied', [
            'user_id' => $user->id,
            'permission' => $permission,
            'reason' => $reason,
        ]);
    }
}
