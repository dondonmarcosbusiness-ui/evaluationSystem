<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Role CRUD + role permission matrix.
 *
 * Security rules (server-side only — never trust client state):
 *  - routes enforce role.* / permission.manage (fail closed)
 *  - a user can never modify a role they themselves hold (no self-escalation)
 *  - permission ids are validated against the catalog (web guard)
 *  - every mutation is transactional and audit-logged with old/new diffs
 */
class RoleController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization)
    {
    }

    public function index()
    {
        return response()->json(
            Role::where('guard_name', 'web')->with('permissions')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'nullable|array',
        ]);

        $permissionIds = $this->validatePermissionIds($request->input('permissions'));

        $this->assertRoleMayHoldPermissions($request->name, $permissionIds);

        $role = DB::transaction(function () use ($request, $permissionIds) {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            return $role;
        });

        AuthorizationService::flushCache();

        AuditLogService::log('role.created', $role, [], [
            'name' => $role->name,
            'permissions' => $permissionIds !== null
                ? $role->permissions()->pluck('name')->all()
                : [],
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function show(Role $role)
    {
        $this->ensureWebGuard($role);

        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);
        $this->denyOwnRole($request, $role, 'role.edit');

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
        ]);

        $permissionIds = $this->validatePermissionIds($request->input('permissions'));

        $this->assertRoleMayHoldPermissions($request->name, $permissionIds, $role->name);

        $old = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->sort()->values()->all(),
        ];

        DB::transaction(function () use ($role, $request, $permissionIds) {
            $role->update(['name' => $request->name]);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }
        });

        AuthorizationService::flushCache();

        $role->unsetRelation('permissions');

        AuditLogService::log('role.updated', $role, $old, [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->sort()->values()->all(),
        ]);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);
        $this->denyOwnRole($request, $role, 'role.delete');

        $old = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->all(),
            'members' => $role->users()->count(),
        ];

        DB::transaction(fn () => $role->delete());

        AuthorizationService::flushCache();

        AuditLogService::log('role.deleted', null, $old, []);

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * Sync the permission matrix for a role. Requires permission.manage
     * (route level) in addition to never touching the caller's own role.
     */
    public function assignPermissions(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);
        $this->denyOwnRole($request, $role, 'permission.manage');

        $request->validate([
            'permissions' => 'required|array',
        ]);

        $permissionIds = $this->validatePermissionIds($request->input('permissions'), required: true);

        $this->assertRoleMayHoldPermissions($role->name, $permissionIds);

        $old = $role->permissions()->pluck('name')->sort()->values()->all();
        $requestedNames = \App\Models\Permission::whereIn('id', $permissionIds)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        // No changes → nothing to sync, nothing to audit.
        if ($old === $requestedNames) {
            return response()->json([
                'message' => 'Permissions are already up to date',
                'role' => $role->load('permissions'),
            ]);
        }

        DB::transaction(fn () => $role->permissions()->sync($permissionIds));

        AuthorizationService::flushCache();

        $role->unsetRelation('permissions');
        $new = $role->permissions()->pluck('name')->sort()->values()->all();

        AuditLogService::log('role.permissions.synced', $role, ['permissions' => $old], [
            'permissions' => $new,
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
        ]);

        return response()->json([
            'message' => 'Permissions synced successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    /**
     * Validate permission identifiers (ids) against the catalog.
     * Returns ids (or null when the key was absent). Throws ValidationException.
     */
    private function validatePermissionIds(mixed $permissions, bool $required = false): ?array
    {
        if ($permissions === null) {
            if ($required) {
                abort(422, 'The permissions field is required.');
            }

            return null;
        }

        foreach ($permissions as $id) {
            if (! is_string($id)) {
                abort(422, 'Invalid permission identifier.');
            }
        }

        $found = \App\Models\Permission::where('guard_name', 'web')
            ->whereIn('id', $permissions)
            ->pluck('id')
            ->all();

        if (count($found) !== count(array_unique($permissions))) {
            abort(422, 'One or more permissions do not exist.');
        }

        return array_values($found);
    }

    /**
     * Students may only ever hold the evaluation permissions from the
     * allowlist — system-control permissions exist for faculty/staff.
     * Checked against the role name both before create and before sync.
     */
    private function assertRoleMayHoldPermissions(
        ?string $roleName,
        ?array $permissionIds,
        ?string $currentName = null
    ): void {
        if ($permissionIds === null) {
            return;
        }

        if ($roleName !== 'Student' && $currentName !== 'Student') {
            return;
        }

        $allowlist = config('authorization.student_permission_allowlist', []);
        $names = \App\Models\Permission::whereIn('id', $permissionIds)->pluck('name')->all();
        $disallowed = array_values(array_diff($names, $allowlist));

        if ($disallowed !== []) {
            abort(
                422,
                'Students may only hold evaluation permissions (' . implode(', ', $allowlist) . '). '
                . 'Not allowed: ' . implode(', ', $disallowed) . '.'
            );
        }
    }

    private function ensureWebGuard(Role $role): void
    {
        if ($role->guard_name !== 'web') {
            abort(404);
        }
    }

    /**
     * Privilege-escalation guard: a user may never modify a role they hold.
     */
    private function denyOwnRole(Request $request, Role $role, string $ability): void
    {
        $actor = $request->user();

        if ($actor && $actor->roles()->whereKey($role->getKey())->exists()) {
            $this->authorization->denyAndAbort(
                $actor,
                $ability,
                'self_role_modification_blocked'
            );
        }
    }
}
