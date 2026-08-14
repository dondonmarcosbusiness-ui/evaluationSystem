<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public static function log(
        string $action,
        $model = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
    ): ?AuditLog {
        $request ??= request();

        $user = Auth::user();

        if (!$user) {
            return null;
        }

        return AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public static function getRecent(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function getModelLogs(string $modelType, string $modelId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::with('user')
            ->where('auditable_type', $modelType)
            ->where('auditable_id', $modelId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
