<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupUploadTest extends TestCase
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

    private function makeUserWithoutPermission(): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'Faculty',
            'email' => 'faculty@test.com',
            'password' => 'password',
            'role' => 'faculty',
            'is_active' => true,
        ]);
    }

    private function sqlBackupFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bkp') . '.sql';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'backup_2026_01_01_10-00-00.sql', 'text/plain', null, true);
    }

    private function sampleDump(): string
    {
        return <<<'SQL'
-- Database Backup: evaluation_system
-- Generated: 2026-01-01 10:00:00

DROP TABLE IF EXISTS uploaded_backup_samples;
CREATE TABLE uploaded_backup_samples (id INTEGER PRIMARY KEY, name TEXT);
INSERT INTO uploaded_backup_samples (id, name) VALUES (1, 'restored-one');
INSERT INTO uploaded_backup_samples (id, name) VALUES (2, 'restored-two');
SQL;
    }

    public function test_upload_stores_the_file_and_restores_its_data(): void
    {
        $admin = $this->makeAdmin();

        DB::statement('CREATE TABLE uploaded_backup_samples (id INTEGER PRIMARY KEY, name TEXT)');
        DB::table('uploaded_backup_samples')->insert(['name' => 'current-data']);

        $res = $this->actingAs($admin, 'sanctum')->post('/api/backups/upload', [
            'file' => $this->sqlBackupFile($this->sampleDump()),
            'password' => 'password',
        ]);

        $res->assertOk();
        $this->assertSame('Backup uploaded and restored successfully', $res->json('message'));

        $files = Storage::files('backups');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('backup_uploaded_', basename($files[0]));
        $this->assertStringEndsWith('.sql', basename($files[0]));

        $this->assertSame(
            ['restored-one', 'restored-two'],
            DB::table('uploaded_backup_samples')->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_upload_with_wrong_password_is_rejected_and_stores_nothing(): void
    {
        $admin = $this->makeAdmin();

        DB::statement('CREATE TABLE uploaded_backup_samples (id INTEGER PRIMARY KEY, name TEXT)');
        DB::table('uploaded_backup_samples')->insert(['name' => 'current-data']);

        $res = $this->actingAs($admin, 'sanctum')->post('/api/backups/upload', [
            'file' => $this->sqlBackupFile($this->sampleDump()),
            'password' => 'wrong-password',
        ]);

        $res->assertStatus(422);
        $this->assertSame([], Storage::files('backups'));
        $this->assertSame(
            ['current-data'],
            DB::table('uploaded_backup_samples')->pluck('name')->all()
        );
    }

    public function test_upload_rejects_a_file_that_is_not_a_backup(): void
    {
        $admin = $this->makeAdmin();

        $res = $this->actingAs($admin, 'sanctum')->post('/api/backups/upload', [
            'file' => $this->sqlBackupFile('this is definitely not a sql dump'),
            'password' => 'password',
        ]);

        $res->assertStatus(422);
        $this->assertSame('The uploaded file is not a valid backup', $res->json('message'));
        $this->assertSame([], Storage::files('backups'));
    }

    public function test_upload_requires_a_file(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/backups/upload', [
            'password' => 'password',
        ])->assertStatus(422);

        $this->assertSame([], Storage::files('backups'));
    }

    public function test_upload_requires_backup_manage_permission(): void
    {
        $user = $this->makeUserWithoutPermission();

        $this->actingAs($user, 'sanctum')->post('/api/backups/upload', [
            'file' => $this->sqlBackupFile($this->sampleDump()),
            'password' => 'password',
        ])->assertForbidden();

        $this->assertSame([], Storage::files('backups'));
    }

    public function test_failed_restore_removes_the_uploaded_file(): void
    {
        $admin = $this->makeAdmin();

        $brokenDump = <<<'SQL'
-- Database Backup: evaluation_system
DROP TABLE IF EXISTS uploaded_backup_samples;
CREATE TABLE uploaded_backup_samples (id INTEGER PRIMARY KEY, name TEXT);
INSERT INTO not_a_real_table VALUES (1, 'boom');
SQL;

        $res = $this->actingAs($admin, 'sanctum')->post('/api/backups/upload', [
            'file' => $this->sqlBackupFile($brokenDump),
            'password' => 'password',
        ]);

        $res->assertStatus(500);
        $this->assertSame([], Storage::files('backups'));
    }
}
