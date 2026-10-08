<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use App\Services\EvaluateeService;
use App\Services\EvaluationScheduleService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Settings keys owned by the scheduling system. They are returned read-only
     * (evaluation_status is computed from the schedules) and never writable
     * through this endpoint, so a Settings save can't clobber them.
     */
    private const INTERNAL_KEYS = ['evaluation_status', 'evaluation_schedule_snapshot'];

    public function __construct(
        protected EvaluationScheduleService $schedules,
        protected EvaluateeService $evaluatees,
    ) {
    }

    public function index(Request $request)
    {
        // Copy before overlaying: cachedAll() may hand back the cached
        // instance itself (array cache driver), which other code reads raw.
        $settings = collect(Setting::cachedAll()->all());

        // Effective evaluation window state (schedule-aware) — consumers such as
        // the dashboard CTA read this key without knowing about schedules.
        $settings->put('evaluation_status', $this->effectiveStatusFor($request->user()));

        return response()->json($settings);
    }

    /**
     * Window the viewer can act on.
     *
     * Students get the status of the departments their evaluatees belong to —
     * the exact set /evaluate lists — so closing their department disables the
     * dashboard CTA even while another department's override stays open (and an
     * unrelated open department never re-enables it). With no evaluatees built
     * yet the student's own course department decides, then the default row.
     * Everyone else sees the institution-wide status.
     */
    private function effectiveStatusFor(?User $user): string
    {
        if ($user === null || $user->role !== 'student') {
            return $this->schedules->globalEffectiveStatus();
        }

        $departments = $this->studentEvaluateeDepartments($user);
        if ($departments === []) {
            $departments = $this->studentHomeDepartments($user);
        }

        $resolve = $this->schedules->resolver();

        if ($departments === []) {
            // No department context at all: the default row decides (or the
            // legacy switch while no schedules exist).
            return $resolve(null);
        }

        foreach ($departments as $department) {
            if ($resolve($department) === EvaluationScheduleService::OPEN) {
                return EvaluationScheduleService::OPEN;
            }
        }

        return EvaluationScheduleService::CLOSED;
    }

    /**
     * Departments of the student's own course — the window that decides the
     * CTA when no evaluatees are built yet (no assignments, period not
     * configured), so a BEED student follows Education instead of whatever
     * other department happens to be open.
     *
     * @return array<int, string>
     */
    private function studentHomeDepartments(User $user): array
    {
        $student = $user->student;
        if ($student === null) {
            return [];
        }

        $courseNames = array_values(array_unique(array_filter(
            [$student->course, $student->section_relationship?->course?->name],
            fn ($name) => is_string($name) && trim($name) !== ''
        )));

        $departments = Course::query()
            ->whereIn('name', $courseNames)
            ->pluck('department')
            ->filter(fn ($department) => is_string($department) && trim($department) !== '')
            ->map(fn ($department) => trim($department))
            ->all();

        $sectionDepartment = $student->section_relationship?->course?->department;
        if (is_string($sectionDepartment) && trim($sectionDepartment) !== '') {
            $departments[] = trim($sectionDepartment);
        }

        return array_values(array_unique($departments));
    }

    /**
     * Unique departments feeding this student's evaluatee list.
     *
     * @return array<int, string>
     */
    private function studentEvaluateeDepartments(User $user): array
    {
        try {
            $config = Setting::cachedAll();
            $evaluatees = $this->evaluatees->build(
                $user,
                $config->get('active_semester'),
                $config->get('active_academic_year')
            );
        } catch (\Throwable $e) {
            return [];
        }

        $departments = [];
        foreach ($evaluatees as $evaluatee) {
            $department = $evaluatee['department'] ?? null;
            if (is_string($department) && trim($department) !== '') {
                $departments[trim($department)] = true;
            }
        }

        return array_keys($departments);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.archived_semester_options' => 'sometimes|array',
            'settings.archived_semester_options.*' => 'string|max:50',
            'settings.archived_academic_year_options' => 'sometimes|array',
            'settings.archived_academic_year_options.*' => 'string|max:20',
        ]);

        $settings = $request->settings;

        $clean = fn($v) => is_string($v) ? trim($v) : $v;
        $asList = function ($v) use ($clean) {
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $v = is_array($decoded) ? $decoded : [$v];
            }
            if (!is_array($v)) return [];
            $out = [];
            foreach ($v as $item) {
                $item = $clean($item);
                if (is_string($item) && $item !== '' && !in_array($item, $out, true)) $out[] = $item;
            }
            return $out;
        };

        $semesterOptions = $asList($settings['semester_options'] ?? []);
        $archivedSemesters = $asList($settings['archived_semester_options'] ?? []);
        $yearOptions = $asList($settings['academic_year_options'] ?? []);
        $archivedYears = $asList($settings['archived_academic_year_options'] ?? []);
        $activeSemester = isset($settings['active_semester']) ? $clean($settings['active_semester']) : null;
        $activeYear = isset($settings['active_academic_year']) ? $clean($settings['active_academic_year']) : null;

        // The active period must never sit in an archive list — reject raw
        // conflicts explicitly before the self-healing normalization below.
        if (is_string($activeSemester) && $activeSemester !== '' && in_array($activeSemester, $archivedSemesters, true)) {
            return response()->json(['message' => 'Active semester cannot be an archived semester. Restore it first.'], 422);
        }
        if (is_string($activeYear) && $activeYear !== '' && in_array($activeYear, $archivedYears, true)) {
            return response()->json(['message' => 'Active academic year cannot be an archived year. Restore it first.'], 422);
        }

        // Archived values must never overlap active dropdowns. The active
        // period is always usable, so force it back into the active lists.
        $archivedSemesters = array_values(array_diff($archivedSemesters, $semesterOptions));
        $archivedYears = array_values(array_diff($archivedYears, $yearOptions));
        if (is_string($activeSemester) && $activeSemester !== '') {
            if (!in_array($activeSemester, $semesterOptions, true)) $semesterOptions[] = $activeSemester;
            $archivedSemesters = array_values(array_diff($archivedSemesters, [$activeSemester]));
        }
        if (is_string($activeYear) && $activeYear !== '') {
            if (!in_array($activeYear, $yearOptions, true)) $yearOptions[] = $activeYear;
            $archivedYears = array_values(array_diff($archivedYears, [$activeYear]));
        }

        $settings['semester_options'] = $semesterOptions;
        $settings['archived_semester_options'] = $archivedSemesters;
        $settings['academic_year_options'] = $yearOptions;
        $settings['archived_academic_year_options'] = $archivedYears;

        foreach ($settings as $key => $value) {
            // Scheduling owns these keys: evaluation_status is computed from
            // the schedules (returned by index(), never writable here) and the
            // notification snapshot must not be clobbered by a Settings save.
            if (in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::forgetCache();

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
