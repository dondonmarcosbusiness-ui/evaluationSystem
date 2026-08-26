<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Services\BackupRetentionService;
use Carbon\Carbon;

class BackupController extends Controller
{
    protected $backupPath = 'backups';

    public function __construct(
        protected BackupRetentionService $backupRetention
    ) {}

    public function index()
    {
        if (!Storage::exists($this->backupPath)) {
            Storage::makeDirectory($this->backupPath);
        }

        $files = Storage::files($this->backupPath);
        $backups = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => $this->formatBytes(Storage::size($file)),
                    'raw_size' => Storage::size($file),
                    'created_at' => Carbon::createFromTimestamp(Storage::lastModified($file))->toDateTimeString(),
                    'timestamp' => Storage::lastModified($file)
                ];
            }
        }

        // Sort by newest first
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        $autoBackup = Setting::cachedAll()->get('auto_backup');
        $lastBackup = count($backups) > 0 ? $backups[0]['created_at'] : null;

        return response()->json([
            'backups' => $backups,
            'auto_backup' => $autoBackup ? filter_var($autoBackup, FILTER_VALIDATE_BOOLEAN) : false,
            'last_backup' => $lastBackup
        ]);
    }

    public function create()
    {
        try {
            if (!Storage::exists($this->backupPath)) {
                Storage::makeDirectory($this->backupPath);
            }

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $path = $this->backupPath . '/' . $filename;

            $pdo = DB::connection()->getPdo();
            $dbName = config('database.connections.mysql.database');

            $sql = '';
            $sql .= "-- Database Backup: {$dbName}\n";
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
                    $placeholders = implode(', ', array_fill(0, $columnCount, '?'));
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

            $deletedBackups = $this->backupRetention->enforce($this->backupPath);

            return response()->json([
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'deleted_backups' => $deletedBackups,
            ]);
        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create backup: ' . $e->getMessage()], 500);
        }
    }

    public function download($filename)
    {
        $path = $this->backupPath . '/' . $filename;
        if (!Storage::exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::download($path);
    }

    public function delete($filename)
    {
        $path = $this->backupPath . '/' . $filename;
        if (!Storage::exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        Storage::delete($path);
        return response()->json(['message' => 'Backup deleted successfully']);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = $request->user();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password verification'], 422);
        }

        $filename = $request->filename;
        $path = $this->backupPath . '/' . $filename;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Backup file not found'], 404);
        }

        try {
            $sqlContent = Storage::get($path);
            $pdo = DB::connection()->getPdo();

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $statements = $this->splitSqlStatements($sqlContent);

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !str_starts_with($statement, '--') && $statement !== 'SET FOREIGN_KEY_CHECKS = 0' && $statement !== 'SET FOREIGN_KEY_CHECKS = 1') {
                    $pdo->exec($statement);
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            return response()->json(['message' => 'Database restored successfully']);
        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
        }
    }

    public function toggleAutoBackup(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);

        Setting::updateOrCreate(
            ['key' => 'auto_backup'],
            ['value' => $request->enabled ? '1' : '0']
        );

        Setting::forgetCache();

        return response()->json(['message' => 'Auto-backup setting updated']);
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                $escaped = true;
                $current .= $char;
                continue;
            }

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
                continue;
            }

            if ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
                continue;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $trimmed = trim($current);
                if (!empty($trimmed)) {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
