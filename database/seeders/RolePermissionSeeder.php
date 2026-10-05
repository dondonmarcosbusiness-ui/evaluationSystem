<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

/**
 * Seeds the namespaced permission catalog and the default role grants from
 * config/authorization.php (single source of truth).
 *
 * Defaults preserve the previous effective access exactly:
 *   Admin   = every permission EXCEPT giving/submitting evaluations
 *   Faculty = view own evaluations + own reports (dashboard is role-based)
 *   Student = give evaluations
 *
 * Also keeps the legacy users.role (admin|faculty|student) -> Spatie role
 * sync so existing accounts continue to work unchanged.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        // Reset cached roles and permissions when the cache store is already available.
        $cacheDriver = config('cache.default');
        $cacheTable = config('cache.stores.database.table', 'cache');

        if ($cacheDriver === 'database' && ! Schema::hasTable($cacheTable)) {
            // Skip cache invalidation until the Laravel cache table exists.
        } else {
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $catalog = config('authorization.permissions', []);
        $defaults = config('authorization.role_defaults', []);

        // Permissions live on the web guard only — that is the guard runtime
        // checks resolve against (legacy sanctum duplicates are never enforced).
        foreach (array_keys($catalog) as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $webPermissionIds = fn (string $role): array => $this->defaultPermissionIds($role, $defaults);

        foreach (['web', 'sanctum'] as $guard) {
            $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
            $facultyRole = Role::firstOrCreate(['name' => 'Faculty', 'guard_name' => $guard]);
            $studentRole = Role::firstOrCreate(['name' => 'Student', 'guard_name' => $guard]);

            if ($guard === 'web') {
                $adminRole->permissions()->sync($webPermissionIds('Admin'));
                $facultyRole->permissions()->sync($webPermissionIds('Faculty'));
                $studentRole->permissions()->sync($webPermissionIds('Student'));
            } else {
                // Sanctum-guard roles are never resolved during permission
                // checks; keep them permission-less to avoid duplicate rows.
                $adminRole->permissions()->sync([]);
                $facultyRole->permissions()->sync([]);
                $studentRole->permissions()->sync([]);
            }
        }

        // Sync all existing users to their respective Spatie roles
        $users = User::all();
        $adminRoleIds = Role::where('name', 'Admin')->pluck('id')->toArray();
        $facultyRoleIds = Role::where('name', 'Faculty')->pluck('id')->toArray();
        $studentRoleIds = Role::where('name', 'Student')->pluck('id')->toArray();

        foreach ($users as $user) {
            if ($user->role === 'admin') {
                $user->roles()->sync($adminRoleIds);
                $user->permissions()->detach(); // Admins should only have role permissions
            } elseif ($user->role === 'faculty') {
                $user->roles()->sync($facultyRoleIds);
            } elseif ($user->role === 'student') {
                $user->roles()->sync($studentRoleIds);
            }
        }

        // Flush AFTER the pivot syncs above — Spatie does not invalidate its
        // cache on relation syncs, and reads during seeding would otherwise
        // repopulate it from a half-synced state.
        if ($cacheDriver === 'database' && ! Schema::hasTable($cacheTable)) {
            // Skip cache invalidation until the Laravel cache table exists.
        } else {
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Resolve the configured default permission ids for a role.
     */
    private function defaultPermissionIds(string $role, array $defaults): array
    {
        $rule = $defaults[$role] ?? ['only' => []];

        $query = Permission::where('guard_name', 'web');

        if (isset($rule['except'])) {
            $query->whereNotIn('name', $rule['except']);
        } else {
            $query->whereIn('name', $rule['only'] ?? []);
        }

        return $query->pluck('id')->all();
    }
}
