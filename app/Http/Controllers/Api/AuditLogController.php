<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only audit log viewer (requires permission.manage via route).
 * Intentionally exposes no password/token/secret material — audit rows
 * only store action, actor, target and old/new values.
 */
class AuditLogController extends Controller
{
    /**
     * Columns the audit log list may be ordered by. "user" resolves through
     * a correlated sub-query on the users table (same pattern as Faculty).
     */
    private function auditLogSortable(): array
    {
        return [
            'created_at' => 'audit_logs.created_at',
            'action' => 'audit_logs.action',
            'user' => User::select('name')->whereColumn('users.id', 'audit_logs.user_id'),
            'ip_address' => 'audit_logs.ip_address',
        ];
    }

    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

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

        if ($request->filled('query')) {
            $search = $request->string('query');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = $request->query('sort_by');
        $sortable = $this->auditLogSortable();

        if ($sortBy && isset($sortable[$sortBy])) {
            $direction = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortable[$sortBy], $direction)->orderBy('audit_logs.id', $direction);
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
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
