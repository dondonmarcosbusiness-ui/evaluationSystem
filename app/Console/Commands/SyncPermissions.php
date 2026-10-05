<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--forget-cache : Deprecated — the cache is always refreshed}';

    protected $description = 'Upsert the permission catalog from config/authorization.php into the database (web guard)';

    public function handle(): int
    {
        if (! Schema::hasTable('permissions')) {
            $this->error('permissions table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $catalog = config('authorization.permissions', []);

        if (empty($catalog)) {
            $this->error('config/authorization.php returned no permissions.');

            return self::FAILURE;
        }

        $created = [];

        foreach (array_keys($catalog) as $name) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        // Always invalidate the Spatie cache: pivot syncs elsewhere in the
        // app do not flush it, so this doubles as the recovery command for
        // stale grants ("permission granted but still 403").
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            // Cache store may not be available yet (fresh install).
        }

        $this->info(sprintf(
            'Catalog sync complete: %d permissions in catalog, %d newly created.',
            count($catalog),
            count($created),
        ));

        foreach ($created as $name) {
            $this->line('  + '.$name);
        }

        return self::SUCCESS;
    }
}
