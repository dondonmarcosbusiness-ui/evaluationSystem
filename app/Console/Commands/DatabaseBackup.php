<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Services\BackupRetentionService;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Generate a database backup if auto-backup is enabled';

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
            $path = $backupPath . '/' . $filename;

            $pdo = DB::connection()->getPdo();
            $dbName = config('database.connections.mysql.database');

            $sql = '';
            $sql .= "-- Auto Backup: {$dbName}\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- ==========================================\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_NUM);

            foreach ($tables as $tableRow) {
                $table = $tableRow[0];

                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $createTable[1] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_NUM);
                if (!empty($rows)) {
                    $columnCount = count($rows[0]);
                    $sql .= "INSERT INTO `{$table}` VALUES\n";

                    $chunks = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        $valueStrings = [];
                        foreach ($chunk as $row) {
                            $escaped = array_map(function ($val) use ($pdo) {
                                if ($val === null) {
                                    return 'NULL';
                                }
                                return $pdo->quote($val);
                            }, $row);
                            $valueStrings[] = '(' . implode(', ', $escaped) . ')';
                        }
                        $sql .= implode(",\n", $valueStrings) . ";\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            Storage::put($path, $sql);

            $this->info("Backup created: {$filename}");
            Log::info("Automated backup successful: {$filename}");

            $deleted = app(BackupRetentionService::class)->enforce($backupPath);
            if ($deleted !== []) {
                $this->info('Retention policy: removed oldest backup(s): ' . implode(', ', $deleted));
            }
        } catch (\Exception $e) {
            Log::error('Automated backup error: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());
        }
    }
}
