<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'backup.manage', 'guard_name' => 'web']);
        $user->givePermissionTo('backup.manage');

        return $user;
    }

    private function putBackup(string $filename, string $contents): void
    {
        Storage::makeDirectory('backups');
        Storage::put('backups/' . $filename, $contents);
    }

    public function test_restore_repairs_legacy_multi_chunk_inserts(): void
    {
        $admin = $this->makeAdmin();

        DB::statement('CREATE TABLE legacy_samples (id INTEGER PRIMARY KEY, name TEXT)');

        // Shape produced by backups generated before the chunk-header fix:
        // only the first 100-row chunk carries the INSERT INTO ... VALUES header.
        $dump = <<<'SQL'
-- Database Backup: legacy
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `legacy_samples`;
CREATE TABLE `legacy_samples` (`id` INTEGER PRIMARY KEY, `name` TEXT);

INSERT INTO `legacy_samples` VALUES
(1, 'first'),
(2, 'second');
(3, 'third'),
(4, 'fourth');

SET FOREIGN_KEY_CHECKS = 1;
SQL;

        $this->putBackup('backup_legacy.sql', $dump);

        $res = $this->actingAs($admin, 'sanctum')->post('/api/backups/restore', [
            'filename' => 'backup_legacy.sql',
            'password' => 'password',
        ]);

        $res->assertOk();
        $this->assertSame(
            ['first', 'second', 'third', 'fourth'],
            DB::table('legacy_samples')->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_restore_accepts_per_chunk_insert_headers(): void
    {
        $admin = $this->makeAdmin();

        DB::statement('CREATE TABLE chunked_samples (id INTEGER PRIMARY KEY, name TEXT)');

        // Shape produced by the fixed generator: every chunk repeats the header.
        $dump = <<<'SQL'
-- Database Backup: fixed
DROP TABLE IF EXISTS `chunked_samples`;
CREATE TABLE `chunked_samples` (`id` INTEGER PRIMARY KEY, `name` TEXT);

INSERT INTO `chunked_samples` VALUES
(1, 'first');
INSERT INTO `chunked_samples` VALUES
(2, 'second');
SQL;

        $this->putBackup('backup_fixed.sql', $dump);

        $this->actingAs($admin, 'sanctum')->post('/api/backups/restore', [
            'filename' => 'backup_fixed.sql',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(
            ['first', 'second'],
            DB::table('chunked_samples')->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_restore_requires_password_and_permission(): void
    {
        $admin = $this->makeAdmin();
        $this->putBackup('backup_legacy.sql', "SELECT 1;\n");

        $this->actingAs($admin, 'sanctum')->post('/api/backups/restore', [
            'filename' => 'backup_legacy.sql',
            'password' => 'wrong',
        ])->assertStatus(422);

        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'Faculty',
            'email' => 'faculty@test.com',
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')->post('/api/backups/restore', [
            'filename' => 'backup_legacy.sql',
            'password' => 'password',
        ])->assertForbidden();
    }
}
