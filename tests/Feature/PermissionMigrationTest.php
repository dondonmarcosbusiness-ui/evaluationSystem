<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionMigrationTest extends TestCase
{
    public function test_permission_migration_can_run_twice_without_error(): void
    {
        $migration = require base_path('database/migrations/2026_03_22_083540_create_permission_tables.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('roles'));
    }

    public function test_permission_migration_ignores_missing_cache_table(): void
    {
        config()->set('cache.default', 'database');

        $migration = require base_path('database/migrations/2026_03_22_083540_create_permission_tables.php');

        $migration->up();

        $this->assertTrue(Schema::hasTable('permissions'));
    }

    public function test_permission_seeder_ignores_missing_cache_table(): void
    {
        config()->set('cache.default', 'database');

        $seeder = new RolePermissionSeeder();

        $seeder->run();

        $this->assertTrue(true);
    }

    public function test_permission_seeder_noops_until_permission_tables_exist(): void
    {
        config()->set('cache.default', 'database');

        $seeder = new RolePermissionSeeder();

        $seeder->run();

        $this->assertFalse(Schema::hasTable('permissions'));
    }
}
