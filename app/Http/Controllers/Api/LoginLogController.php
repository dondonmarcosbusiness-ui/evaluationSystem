<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;

/**
 * Read-only account access log (login successes / failures / lockouts).
 *
 * Route requires permission:permission.manage — the same gate as the audit
 * log viewer. Rows expose identifier, status, ip and user agent only;
 * passwords and tokens are never stored (see LoginLogService).
 */
class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        $summaryRows = $this->filtered($request)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'success' => (int) ($summaryRows['success'] ?? 0),
            'failed' => (int) ($summaryRows['failed'] ?? 0),
            'locked' => (int) ($summaryRows['locked'] ?? 0),
            'inactive' => (int) ($summaryRows['inactive'] ?? 0),
            'total' => (int) $summaryRows->sum(),
        ];

        $logs = $this->filtered($request)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return response()->json(array_merge($logs->toArray(), ['summary' => $summary]));
    }

    private function filtered(Request $request)
    {
        $query = LoginLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->string('to') . ' 23:59:59');
        }

        if ($request->filled('q')) {
            $like = '%' . $request->string('q') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('login_identifier', 'like', $like)
                    ->orWhereHas('user', function ($u) use ($like) {
                        $u->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    });
            });
        }

        return $query;
    }
}
