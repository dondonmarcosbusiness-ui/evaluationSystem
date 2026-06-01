<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    public const MAX_BACKUPS = 5;

    /**
     * Delete oldest backup files until only the newest MAX_BACKUPS remain.
     *
     * @return array<int, string> Basenames of deleted files
     */
    public function enforce(string $backupPath = 'backups'): array
    {
        if (!Storage::exists($backupPath)) {
            return [];
        }

        $files = collect(Storage::files($backupPath))
            ->filter(fn (string $file) => pathinfo($file, PATHINFO_EXTENSION) === 'sql')
            ->map(fn (string $file) => [
                'path' => $file,
                'timestamp' => Storage::lastModified($file),
            ])
            ->sortByDesc('timestamp')
            ->values();

        if ($files->count() <= self::MAX_BACKUPS) {
            return [];
        }

        $deleted = [];

        foreach ($files->slice(self::MAX_BACKUPS) as $file) {
            Storage::delete($file['path']);
            $deleted[] = basename($file['path']);
        }

        if ($deleted !== []) {
            Log::info('Backup retention: removed oldest backup(s)', [
                'deleted' => $deleted,
                'retained' => self::MAX_BACKUPS,
            ]);
        }

        return $deleted;
    }
}
