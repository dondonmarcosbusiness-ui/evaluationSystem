<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission as PermissionModel;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user role / direct-permission assignment.
 *
 * Escalation guards (server-side):
 *  - routes require permission.manage (fail closed)
 *  - a user can never modify their own roles or direct permissions
 *  - every id/name is validated against existing web-guard records
 *  - mutations are transactional and audit-logged with old/new diffs
 */
class UserRoleController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization)
    {
    }

    public function assignRole(Request $request, User $user)
    {
        $this->denySelfModification($request, $user);

        $request->validate([
            'roles' => 'nullable|array',
        ]);

        $roleNames = $request->input('roles', []);
        $this->assertRolesExist($roleNames);
        $this->assertStudentMayReceiveRoles($user, $roleNames);

        $old = $user->getRoleNames()->sort()->values()->all();
        $requested = collect($roleNames)->unique()->sort()->values()->all();

        // No changes → nothing to sync, nothing to audit.
        if ($old === $requested) {
            return response()->json([
                'message' => 'Roles are already up to date',
                'user' => $user->load('roles', 'permissions'),
            ]);
        }

        DB::transaction(fn () => $user->syncRoles($roleNames));

        AuthorizationService::flushCache();

        $new = $user->getRoleNames()->sort()->values()->all();

        AuditLogService::log('user.roles.synced', $user, ['roles' => $old], [
            'roles' => $new,
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
        ]);

        return response()->json([
            'message' => 'Roles assigned successfully',
            'user' => $user->load('roles', 'permissions'),
        ]);
    }

    public function assignPermission(Request $request, User $user)
    {
        $this->denySelfModification($request, $user);

        $request->validate([
            'permissions' => 'nullable|array',
        ]);

        $permissionNames = $request->input('permissions', []);
        $this->assertPermissionsExist($permissionNames);
        $this->assertStudentMayReceivePermissions($user, $permissionNames);

        $old = $user->getPermissionNames()->sort()->values()->all();
        $requested = collect($permissionNames)->unique()->sort()->values()->all();

        // No changes → nothing to sync, nothing to audit.
        if ($old === $requested) {
            return response()->json([
                'message' => 'Permissions are already up to date',
                'user' => $user->load('roles', 'permissions'),
            ]);
        }

        DB::transaction(fn () => $user->syncPermissions($permissionNames));

        AuthorizationService::flushCache();

        $new = $user->getPermissionNames()->sort()->values()->all();

        AuditLogService::log('user.permissions.synced', $user, ['permissions' => $old], [
            'permissions' => $new,
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
        ]);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'user' => $user->load('roles', 'permissions'),
        ]);
    }

    public function getUserPermissions(User $user)
    {
        return response()->json([
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getPermissionNames(),
        ]);
    }

    /**
     * A user must never be able to grant/revoke their own roles or
     * permissions (privilege escalation).
     */
    private function denySelfModification(Request $request, User $user): void
    {
        $actor = $request->user();

        if ($actor && $actor->id === $user->id) {
            $this->authorization->denyAndAbort(
                $actor,
                'permission.manage',
                'self_modification_blocked'
            );
        }
    }

    /**
     * A student user may only be granted direct permissions from the
     * evaluation allowlist — system control belongs to faculty/staff.
     */
    private function assertStudentMayReceivePermissions(User $user, array $permissionNames): void
    {
        if ($user->role !== 'student') {
            return;
        }

        $allowlist = config('authorization.student_permission_allowlist', []);
        $disallowed = array_values(array_diff($permissionNames, $allowlist));

        if ($disallowed !== []) {
            abort(
                422,
                'Students may only receive evaluation permissions (' . implode(', ', $allowlist) . '). '
                . 'Not allowed: ' . implode(', ', $disallowed) . '.'
            );
        }
    }

    /**
     * A student user may only be assigned roles whose effective permissions
     * all fall inside the evaluation allowlist (blocks Admin/Faculty roles).
     */
    private function assertStudentMayReceiveRoles(User $user, array $roleNames): void
    {
        if ($user->role !== 'student') {
            return;
        }

        $allowlist = config('authorization.student_permission_allowlist', []);

        $disallowedRoles = Role::where('guard_name', 'web')
            ->whereIn('name', $roleNames)
            ->with('permissions')
            ->get()
            ->reject(fn (Role $role) => array_diff($role->permissions->pluck('name')->all(), $allowlist) === [])
            ->pluck('name')
            ->all();

        if ($disallowedRoles !== []) {
            abort(
                422,
                'Student users may only hold evaluation roles. Not allowed: '
                . implode(', ', $disallowedRoles) . '.'
            );
        }
    }

    private function assertRolesExist(array $roleNames): void
    {
        foreach ($roleNames as $name) {
            if (! is_string($name)) {
                abort(422, 'Invalid role identifier.');
            }
        }

        $found = Role::where('guard_name', 'web')
            ->whereIn('name', $roleNames)
            ->count();

        if ($found !== count(array_unique($roleNames))) {
            abort(422, 'One or more roles do not exist.');
        }
    }

    private function assertPermissionsExist(array $permissionNames): void
    {
        foreach ($permissionNames as $name) {
            if (! is_string($name)) {
                abort(422, 'Invalid permission identifier.');
            }
        }

        $found = PermissionModel::where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->count();

        if ($found !== count(array_unique($permissionNames))) {
            abort(422, 'One or more permissions do not exist.');
        }
    }
}
