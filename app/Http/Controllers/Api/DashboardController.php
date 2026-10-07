<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoginLogService;
use App\Services\OnlinePresenceService;
use Illuminate\Http\Request;

/**
 * Dashboard summary/status metrics (admin).
 *
 * Route-gated with permission:dashboard.view; access-error figures are only
 * included when the caller also holds permission.manage. Aggregates only —
 * never returns student names or credentials.
 */
class DashboardController extends Controller
{
    private const ONLINE_WINDOW_DEFAULT = 5; // minutes
    private const SERIES_DAYS = 7;

    public function __construct(private readonly OnlinePresenceService $presence)
    {
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $settings = Setting::cachedAll();
        $semester = $settings->get('active_semester');
        $academicYear = $settings->get('active_academic_year');

        $minutes = min(max((int) $request->input('minutes', self::ONLINE_WINDOW_DEFAULT), 1), 60);
        $start = now()->subDays(self::SERIES_DAYS - 1)->startOfDay();
        $labels = $this->seriesLabels($start);

        // ── Evaluation activity for the active period ──
        $period = Evaluation::query();
        if ($semester) {
            $period->where('semester', $semester);
        }
        if ($academicYear) {
            $period->where('academic_year', $academicYear);
        }

        $evaluationsSubmitted = (clone $period)->count();
        $studentsFinished = $this->studentsFinished($semester, $academicYear);

        $evalSeries = $this->dailySeries(Evaluation::query()->where('created_at', '>=', $start));
        $submittedSeries = $this->dailySeries(
            Evaluation::query()->where('created_at', '>=', $start),
            'student_id'
        );

        // ── Access errors (failed / locked / deactivated logins) ──
        $canSeeAccessErrors = (bool) $user->getAllPermissions()->contains('name', 'permission.manage');
        $failedSeries = array_fill(0, self::SERIES_DAYS, 0);
        $failedLogins = null;

        if ($canSeeAccessErrors) {
            $failedSeries = $this->dailySeries(
                LoginLog::query()
                    ->whereIn('status', [
                        LoginLogService::FAILED,
                        LoginLogService::LOCKED,
                        LoginLogService::INACTIVE,
                    ])
                    ->where('created_at', '>=', $start)
            );
            $failedLogins = array_sum($failedSeries);
        }

        // ── Students active online ──
        $online = $this->onlineStudents($minutes);

        // Raise today's peak while the dashboard samples presence.
        $online['peak_today'] = $this->presence->recordPeak($online['total_online']);
        $online['peak_date'] = now()->toDateString();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'semester' => $semester,
                'academic_year' => $academicYear,
            ],
            'series_labels' => $labels,
            'students_finished' => $studentsFinished,
            'students_submitted_series' => $submittedSeries,
            'evaluations_submitted' => $evaluationsSubmitted,
            'evaluations_submitted_series' => $evalSeries,
            'failed_logins' => $failedLogins,
            'failed_logins_series' => $failedSeries,
            'access_errors_visible' => $canSeeAccessErrors,
            'online_students' => $online,
        ]);
    }

    /**
     * Students who evaluated every evaluatee for the period.
     *
     * Backed by the period-scoped flag written on the student's final
     * submission (and by the backfill migration), so the dashboard stays a
     * cheap count instead of rebuilding each student's evaluatee list on every
     * load. Period columns must match; with no active period configured the
     * filter mirrors the evaluation query above and stays unscoped.
     */
    private function studentsFinished(?string $semester, ?string $academicYear): int
    {
        $query = User::query()
            ->where('role', 'student')
            ->whereNotNull('evaluations_completed_at');

        if ($semester) {
            $query->where('evaluations_completed_semester', $semester);
        }
        if ($academicYear) {
            $query->where('evaluations_completed_academic_year', $academicYear);
        }

        return $query->count();
    }

    /**
     * Online = student account that used the API within the window
     * (Sanctum keeps personal_access_tokens.last_used_at fresh per request).
     */
    private function onlineStudents(int $minutes): array
    {
        $cutoff = now()->subMinutes($minutes);

        $courseDepartments = Course::pluck('department', 'name');

        $students = User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->whereHas('student')
            ->with('student.section_relationship.course')
            ->get(['id']);

        $onlineIds = User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->whereHas('student')
            ->whereHas('tokens', fn ($q) => $q->where('last_used_at', '>=', $cutoff))
            ->pluck('id')
            ->flip();

        $byDepartment = [];
        $byYear = [];
        $byDepartmentYear = [];
        $onlineTotal = 0;

        foreach ($students as $studentUser) {
            $student = $studentUser->student;
            $section = $student?->section_relationship;
            $courseName = $section?->course?->name ?? $student?->course;

            $department = $section?->course?->department
                ?? ($courseName !== null ? $courseDepartments->get($courseName) : null)
                ?? 'Unspecified';
            $year = $student?->year_level ?: ($section?->year_level ?: 'Unspecified');

            $isOnline = $onlineIds->has($studentUser->id);
            if ($isOnline) {
                $onlineTotal++;
            }

            $byDepartment[$department] ??= ['department' => $department, 'online' => 0, 'total' => 0];
            $byDepartment[$department]['total']++;
            if ($isOnline) {
                $byDepartment[$department]['online']++;
            }

            $byYear[$year] ??= ['year_level' => $year, 'online' => 0, 'total' => 0];
            $byYear[$year]['total']++;
            if ($isOnline) {
                $byYear[$year]['online']++;
            }

            $key = $department . '|' . $year;
            $byDepartmentYear[$key] ??= [
                'department' => $department,
                'year_level' => $year,
                'online' => 0,
                'total' => 0,
            ];
            $byDepartmentYear[$key]['total']++;
            if ($isOnline) {
                $byDepartmentYear[$key]['online']++;
            }
        }

        $byDepartment = array_values($byDepartment);
        usort($byDepartment, fn ($a, $b) => strnatcasecmp($a['department'], $b['department']));

        $byYear = array_values($byYear);
        usort($byYear, fn ($a, $b) => strnatcasecmp($a['year_level'], $b['year_level']));

        $byDepartmentYear = array_values($byDepartmentYear);
        usort(
            $byDepartmentYear,
            fn ($a, $b) => strnatcasecmp($a['department'], $b['department'])
                ?: strnatcasecmp($a['year_level'], $b['year_level'])
        );

        return [
            'window_minutes' => $minutes,
            'total_online' => $onlineTotal,
            'total_students' => count($students),
            'by_department' => $byDepartment,
            'by_year' => $byYear,
            'by_department_year' => $byDepartmentYear,
        ];
    }

    /** Daily counts (or daily distinct values) for the trailing window. */
    private function dailySeries($query, ?string $distinctColumn = null): array
    {
        $expression = $distinctColumn ? "count(distinct {$distinctColumn})" : 'count(*)';

        $rows = (clone $query)
            ->selectRaw("date(created_at) as bucket, {$expression} as total")
            ->groupByRaw('date(created_at)')
            ->pluck('total', 'bucket')
            ->all();

        $series = [];
        for ($i = 0; $i < self::SERIES_DAYS; $i++) {
            $key = now()->subDays(self::SERIES_DAYS - 1 - $i)->toDateString();
            $series[] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    private function seriesLabels(\DateTimeInterface $start): array
    {
        $labels = [];
        for ($i = 0; $i < self::SERIES_DAYS; $i++) {
            $labels[] = (new \DateTimeImmutable('@' . $start->getTimestamp()))
                ->modify("+{$i} days")
                ->format('M d');
        }

        return $labels;
    }
}
