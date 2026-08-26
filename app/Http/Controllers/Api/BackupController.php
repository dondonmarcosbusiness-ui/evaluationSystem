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
            
            // Ensure the directory exists
            if (!Storage::exists($this->backupPath)) {
                Storage::makeDirectory($this->backupPath);
            }

            $filePath = Storage::path($path);

            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');

            // Find mysqldump path
            $mysqldump = 'mysqldump';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $laragonPaths = glob('C:\laragon\bin\mysql\*\bin\mysqldump.exe');
                $xamppPath = 'C:\xampp\mysql\bin\mysqldump.exe';
                
                if (!empty($laragonPaths)) {
                    $mysqldump = '"' . $laragonPaths[0] . '"';
                } elseif (file_exists($xamppPath)) {
                    $mysqldump = '"' . $xamppPath . '"';
                }
            } else {
                // Linux/macOS: try common paths, `which`, and Nix store
                $commonPaths = [
                    '/usr/bin/mysqldump',
                    '/usr/local/bin/mysqldump',
                    '/opt/homebrew/bin/mysqldump',
                ];
                $found = false;
                foreach ($commonPaths as $path) {
                    if (file_exists($path)) {
                        $mysqldump = $path;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    exec('which mysqldump 2>/dev/null || bash -lc "which mysqldump" 2>/dev/null', $whichOutput, $whichReturn);
                    if ($whichReturn === 0 && !empty($whichOutput)) {
                        $mysqldump = trim($whichOutput[0]);
                    }
                }
                if ($mysqldump === 'mysqldump') {
                    // Fallback: search Nix store for mysqldump
                    exec('find /nix/store -maxdepth 4 -name mysqldump -type f 2>/dev/null | head -1', $nixOutput, $nixReturn);
                    if ($nixReturn === 0 && !empty($nixOutput)) {
                        $mysqldump = trim($nixOutput[0]);
                    }
                }
            }

            chdir(base_path());
            Log::info('Current Working Directory: ' . getcwd());
            Log::info('Using mysqldump binary: ' . $mysqldump);

            // Verify mysqldump is available
            exec($mysqldump . ' --version 2>&1', $versionOutput, $versionReturn);
            if ($versionReturn !== 0) {
                throw new \Exception('mysqldump binary not found at "' . $mysqldump . '". Ensure mysql-client is installed on the server.');
            }

            // Use relative path to avoid issues with % in absolute project path on Windows
            $relativeFilePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', Storage::path($path));
            // Normalize path separators for Windows
            $relativeFilePath = str_replace('/', DIRECTORY_SEPARATOR, $relativeFilePath);

            $command = sprintf(
                '%s --user=%s --password=%s --host=%s --result-file=%s %s 2>&1',
                $mysqldump,
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($relativeFilePath),
                escapeshellarg($dbName)
            );

            Log::info('Backup command: ' . $command);
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $errorOutput = implode("\n", $output);
                Log::error('mysqldump failed. Exit code: ' . $returnVar . '. Output: ' . $errorOutput);
                throw new \Exception("mysqldump failed with exit code {$returnVar}. Error: {$errorOutput}");
            }

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

        // Verify admin password for security
        $user = $request->user();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password verification'], 422);
        }

        $filename = $request->filename;
        $path = $this->backupPath . '/' . $filename;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Backup file not found'], 404);
        }

        $filePath = Storage::path($path);

        try {
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');

            $mysql = 'mysql';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $laragonPaths = glob('C:\laragon\bin\mysql\*\bin\mysql.exe');
                $xamppPath = 'C:\xampp\mysql\bin\mysql.exe';
                
                if (!empty($laragonPaths)) {
                    $mysql = '"' . $laragonPaths[0] . '"';
                } elseif (file_exists($xamppPath)) {
                    $mysql = '"' . $xamppPath . '"';
                }
            } else {
                $commonPaths = [
                    '/usr/bin/mysql',
                    '/usr/local/bin/mysql',
                    '/opt/homebrew/bin/mysql',
                ];
                $found = false;
                foreach ($commonPaths as $path) {
                    if (file_exists($path)) {
                        $mysql = $path;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    exec('which mysql 2>/dev/null || bash -lc "which mysql" 2>/dev/null', $whichOutput, $whichReturn);
                    if ($whichReturn === 0 && !empty($whichOutput)) {
                        $mysql = trim($whichOutput[0]);
                    }
                }
                if ($mysql === 'mysql') {
                    exec('find /nix/store -maxdepth 4 -name mysql -type f 2>/dev/null | head -1', $nixOutput, $nixReturn);
                    if ($nixReturn === 0 && !empty($nixOutput)) {
                        $mysql = trim($nixOutput[0]);
                    }
                }
            }

            chdir(base_path());
            // Use relative path to avoid issues with % in absolute project path on Windows
            $relativeFilePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', Storage::path($path));
            // Normalize path separators for Windows
            $relativeFilePath = str_replace('/', DIRECTORY_SEPARATOR, $relativeFilePath);

            $command = sprintf(
                '%s --user=%s --password=%s --host=%s %s < %s 2>&1',
                $mysql,
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbName),
                escapeshellarg($relativeFilePath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $errorOutput = implode("\n", $output);
                Log::error('Database restore failed. Exit code: ' . $returnVar . '. Output: ' . $errorOutput);
                throw new \Exception("Database restoration failed with exit code {$returnVar}. Error: {$errorOutput}");
            }

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
