<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-time migration: legacy flat permissions (manage_faculty, view_reports,
 * give_evaluations, ...) are expanded into the new namespaced, granular
 * capability permissions defined in config/authorization.php.
 *
 * Backward compatibility: every role/user that held a legacy permission
 * receives the full expansion of that permission, so effective access is
 * preserved exactly (see config/authorization.php 'legacy_map').
 *
 * Only web-guard permission rows are migrated — runtime permission checks
 * always resolve against the default 'web' guard (the legacy 'sanctum'
 * duplicate rows were never enforced).
 *
 * Idempotent: re-running is a no-op once legacy rows are gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $map = config('authorization.legacy_map', []);

        if (empty($map)) {
            return;
        }

        $legacyRows = Permission::whereIn('name', array_keys($map))
            ->where('guard_name', 'web')
            ->get();

        if ($legacyRows->isEmpty()) {
            return; // Already migrated (or fresh install) — nothing to do.
        }

        DB::transaction(function () use ($map, $legacyRows): void {
            // 1. Ensure every target permission exists (web guard).
            $targetNames = collect($map)->flatten()->unique()->values();
            $targetIds = [];

            foreach ($targetNames as $name) {
                $permission = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $targetIds[$name] = $permission->id;
            }

            // 2. Expand role grants (role_has_permissions) without detaching
            //    any other grants the role already has.
            $legacyIds = $legacyRows->pluck('id');
            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', $legacyIds)
                ->pluck('role_id')
                ->unique();

            foreach ($roleIds as $roleId) {
                $role = Role::find($roleId);

                if (! $role) {
                    continue;
                }

                $expansions = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->whereIn('permission_id', $legacyIds)
                    ->pluck('permission_id')
                    ->map(fn ($id) => $legacyRows->firstWhere('id', $id)?->name)
                    ->filter()
                    ->flatMap(fn ($name) => $map[$name] ?? [])
                    ->unique()
                    ->map(fn ($name) => $targetIds[$name])
                    ->values()
                    ->all();

                if ($expansions !== []) {
                    $role->permissions()->syncWithoutDetaching($expansions);
                }
            }

            // 3. Expand direct user grants (model_has_permissions) the same way.
            $directRows = DB::table('model_has_permissions')
                ->whereIn('permission_id', $legacyIds)
                ->where('model_type', User::class)
                ->get();

            foreach ($directRows->groupBy('model_id') as $userId => $rows) {
                $expansions = $rows
                    ->map(fn ($row) => $legacyRows->firstWhere('id', $row->permission_id)?->name)
                    ->filter()
                    ->flatMap(fn ($name) => $map[$name] ?? [])
                    ->unique()
                    ->map(fn ($name) => $targetIds[$name])
                    ->values()
                    ->all();

                $user = User::find($userId);

                if ($user && $expansions !== []) {
                    $user->permissions()->syncWithoutDetaching($expansions);
                }
            }

            // 4. Remove legacy permission rows (both guards). Pivot rows are
            //    removed by foreign-key cascade; expansions are already in place.
            Permission::whereIn('name', array_keys($map))->delete();
        });

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
            // Cache store may not exist yet on a fresh install.
        }
    }

    public function down(): void
    {
        // Irreversible by design: the expansion cannot be collapsed back into
        // legacy names without knowing the original assignments, which this
        // migration intentionally normalizes.
    }
};
