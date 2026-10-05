<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use App\Models\Permission;

/**
 * Permission catalog API.
 *
 * Catalog permissions are managed by config/authorization.php +
 * `php artisan permissions:sync` — deleting them would brick protected
 * routes (default deny), so only ad-hoc (non-catalog) permissions can be
 * removed here. All routes require permission.manage (fail closed).
 */
class PermissionController extends Controller
{
    public function index()
    {
        $catalog = config('authorization.permissions', []);

        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission) use ($catalog) {
                $meta = $catalog[$permission->name] ?? [
                    'module' => 'custom',
                    'action' => $permission->name,
                    'label' => $permission->name,
                ];

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'module' => $meta['module'] ?? 'custom',
                    'action' => $meta['action'] ?? $permission->name,
                    'label' => $meta['label'] ?? $permission->name,
                    'scope_for' => $meta['scope_for'] ?? null,
                    'scope' => $meta['scope'] ?? null,
                    'reserved' => (bool) ($meta['reserved'] ?? false),
                    'catalog' => isset($catalog[$permission->name]),
                ];
            });

        return response()->json([
            'permissions' => $permissions,
            'modules' => config('authorization.modules', []),
            'student_allowlist' => config('authorization.student_permission_allowlist', []),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|regex:/^[a-z0-9_]+(\.[a-z0-9_]+)+$/',
            'guard_name' => 'nullable|string|in:web',
        ]);

        $permission = Permission::firstOrCreate([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        AuthorizationService::flushCache();

        AuditLogService::log('permission.created', $permission, [], [
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission,
        ], 201);
    }

    public function destroy(Permission $permission)
    {
        $catalog = config('authorization.permissions', []);

        if (isset($catalog[$permission->name])) {
            // Catalog permissions back protected routes; removing one would
            // lock everyone out of the capability (default deny).
            abort(403, 'Catalog permissions cannot be deleted. Remove them from config/authorization.php and run permissions:sync.');
        }

        $old = ['name' => $permission->name, 'guard_name' => $permission->guard_name];

        $permission->delete();

        AuthorizationService::flushCache();

        AuditLogService::log('permission.deleted', null, $old, []);

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
