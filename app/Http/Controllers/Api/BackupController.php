<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
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
                    'created_at' => Carbon::createFromTimestamp(Storage::lastModified($file))->toIso8601String(),
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
                    $chunks = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        $sql .= "INSERT INTO `{$table}` VALUES\n";
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

        if (!$this->passwordMatches($request)) {
            return response()->json(['message' => 'Invalid password verification'], 422);
        }

        $filename = $request->filename;
        $path = $this->backupPath . '/' . $filename;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Backup file not found'], 404);
        }

        try {
            $this->executeSql(Storage::get($path));

            return response()->json(['message' => 'Database restored successfully']);
        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:sql,txt|max:10240',
            'password' => 'required|string',
        ]);

        if (!$this->passwordMatches($request)) {
            return response()->json(['message' => 'Invalid password verification'], 422);
        }

        $sqlContent = file_get_contents($request->file('file'));

        if ($sqlContent === false || !preg_match('/\b(CREATE\s+TABLE|DROP\s+TABLE|INSERT\s+INTO)\b/i', $sqlContent)) {
            return response()->json(['message' => 'The uploaded file is not a valid backup'], 422);
        }

        if (!Storage::exists($this->backupPath)) {
            Storage::makeDirectory($this->backupPath);
        }

        $filename = $this->uniqueFilename();
        $path = $this->backupPath . '/' . $filename;

        try {
            Storage::put($path, $sqlContent);
            $this->executeSql($sqlContent);
        } catch (\Exception $e) {
            if (Storage::exists($path)) {
                Storage::delete($path);
            }

            Log::error('Backup upload failed: ' . $e->getMessage());
            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
        }

        $deletedBackups = $this->backupRetention->enforce($this->backupPath);

        Log::info('Backup uploaded and restored', [
            'filename' => $filename,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Backup uploaded and restored successfully',
            'filename' => $filename,
            'deleted_backups' => $deletedBackups,
        ]);
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

    private function passwordMatches(Request $request): bool
    {
        $user = $request->user();

        return $user && Hash::check($request->password, $user->password);
    }

    private function uniqueFilename(): string
    {
        $base = 'backup_uploaded_' . date('Y-m-d_H-i-s');
        $filename = $base . '.sql';
        $i = 1;

        while (Storage::exists($this->backupPath . '/' . $filename)) {
            $filename = $base . '_' . $i++ . '.sql';
        }

        return $filename;
    }

    private function executeSql(string $sqlContent): void
    {
        $pdo = DB::connection()->getPdo();
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        $insertPrefix = null;

        foreach ($this->splitSqlStatements($sqlContent) as $statement) {
            $statement = trim($statement);

            if ($statement === '' || str_starts_with($statement, '--') || str_starts_with($statement, '#')) {
                continue;
            }

            if (in_array($statement, ['SET FOREIGN_KEY_CHECKS = 0', 'SET FOREIGN_KEY_CHECKS = 1'], true)) {
                continue;
            }

            // Backups generated before this fix only prefixed the first 100-row
            // chunk with INSERT INTO ... VALUES, leaving later chunks as bare
            // value lists. Re-attach the header so those files still restore.
            if (str_starts_with($statement, '(') && $insertPrefix !== null) {
                $statement = $insertPrefix . $statement;
            }

            if (preg_match('/^(INSERT\s+INTO\s+.+?\bVALUES\b)/is', $statement, $match)) {
                $insertPrefix = $match[1] . "\n";
            } else {
                $insertPrefix = null;
            }

            if (!$isMysql && preg_match('/^(SET|USE|LOCK\s+TABLES|UNLOCK\s+TABLES)\b/i', $statement)) {
                continue;
            }

            $pdo->exec($statement);
        }

        if ($isMysql) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
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

            if (!$inSingleQuote && !$inDoubleQuote) {
                $isDashComment = $char === '-'
                    && $i + 1 < $len
                    && $sql[$i + 1] === '-'
                    && ($i + 2 >= $len || ctype_space($sql[$i + 2]));

                if ($char === '#' || $isDashComment) {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }

                if ($char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                    // MySQL executable comments (/*!...*/) keep their inner SQL.
                    if ($i + 2 < $len && $sql[$i + 2] === '!') {
                        $i += 3;
                        while ($i < $len && ctype_digit($sql[$i])) {
                            $i++;
                        }
                        continue;
                    }

                    $i += 2;
                    while ($i < $len && !($sql[$i] === '*' && $i + 1 < $len && $sql[$i + 1] === '/')) {
                        $i++;
                    }
                    $i++;
                    continue;
                }

                if ($char === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
                    $i++;
                    continue;
                }
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
