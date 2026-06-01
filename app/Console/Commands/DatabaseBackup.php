<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Services\BackupRetentionService;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a database backup if auto-backup is enabled';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $autoBackup = Setting::where('key', 'auto_backup')->first();
        if (!$autoBackup || !filter_var($autoBackup->value, FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Auto-backup is disabled.');
            return;
        }

        try {
            $backupPath = 'backups';
            if (!Storage::exists($backupPath)) {
                Storage::makeDirectory($backupPath);
            }

            $filename = 'backup_auto_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/private/' . $backupPath . '/' . $filename);
            
            $absPath = storage_path('app/private/' . $backupPath);
            if (!file_exists($absPath)) {
                mkdir($absPath, 0755, true);
            }

            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');

            $mysqldump = 'mysqldump';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $xamppPath = 'C:\xampp\mysql\bin\mysqldump.exe';
                if (file_exists($xamppPath)) {
                    $mysqldump = '"' . $xamppPath . '"';
                }
            }

            $command = sprintf(
                '%s --user=%s --password=%s --host=%s %s > %s',
                $mysqldump,
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbName),
                escapeshellarg($filePath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->info("Backup created: {$filename}");
                Log::info("Automated backup successful: {$filename}");

                $deleted = app(BackupRetentionService::class)->enforce($backupPath);
                if ($deleted !== []) {
                    $this->info('Retention policy: removed oldest backup(s): ' . implode(', ', $deleted));
                }
            } else {
                Log::error("Automated backup failed with exit code {$returnVar}");
            }
        } catch (\Exception $e) {
            Log::error('Automated backup error: ' . $e->getMessage());
        }
    }
}
