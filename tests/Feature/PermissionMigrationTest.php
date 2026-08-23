<?php

namespace Tests\Feature;

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
}
