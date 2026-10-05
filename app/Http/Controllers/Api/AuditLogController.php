<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Read-only audit log viewer (requires permission.manage via route).
 * Intentionally exposes no password/token/secret material — audit rows
 * only store action, actor, target and old/new values.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->string('to').' 23:59:59');
        }

        $logs = $query->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return response()->json($logs);
    }

    /**
     * Distinct action values for the filter dropdown (permission.manage via route).
     */
    public function actions()
    {
        return response()->json(
            AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')
        );
    }
}
