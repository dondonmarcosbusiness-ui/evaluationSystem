<?php

namespace Tests\Unit;

use App\Services\BackupRetentionService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRetentionServiceTest extends TestCase
{
    private BackupRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->service = new BackupRetentionService();
    }

    public function test_enforce_deletes_oldest_when_more_than_five_backups_exist(): void
    {
        $backupPath = 'backups';
        Storage::makeDirectory($backupPath);

        $timestamps = [
            'backup_oldest.sql' => 1000,
            'backup_older.sql' => 2000,
            'backup_mid.sql' => 3000,
            'backup_newer.sql' => 4000,
            'backup_new.sql' => 5000,
            'backup_newest.sql' => 6000,
            'backup_latest.sql' => 7000,
        ];

        foreach ($timestamps as $filename => $mtime) {
            $path = "{$backupPath}/{$filename}";
            Storage::put($path, 'dump');
            touch(Storage::path($path), $mtime);
        }

        $deleted = $this->service->enforce($backupPath);

        $this->assertCount(2, $deleted);
        $this->assertContains('backup_oldest.sql', $deleted);
        $this->assertContains('backup_older.sql', $deleted);
        $this->assertCount(5, Storage::files($backupPath));
        $this->assertFalse(Storage::exists("{$backupPath}/backup_oldest.sql"));
        $this->assertFalse(Storage::exists("{$backupPath}/backup_older.sql"));
        $this->assertTrue(Storage::exists("{$backupPath}/backup_latest.sql"));
    }

    public function test_enforce_does_nothing_when_five_or_fewer_backups_exist(): void
    {
        $backupPath = 'backups';
        Storage::makeDirectory($backupPath);

        foreach (['backup_a.sql', 'backup_b.sql', 'backup_c.sql'] as $filename) {
            Storage::put("{$backupPath}/{$filename}", 'dump');
        }

        $deleted = $this->service->enforce($backupPath);

        $this->assertSame([], $deleted);
        $this->assertCount(3, Storage::files($backupPath));
    }
}
