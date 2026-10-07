<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Settings keys owned by the scheduling system. They are returned read-only
     * (evaluation_status is computed from the schedules) and never writable
     * through this endpoint, so a Settings save can't clobber them.
     */
    private const INTERNAL_KEYS = ['evaluation_status', 'evaluation_schedule_snapshot'];

    public function __construct(protected \App\Services\EvaluationScheduleService $schedules)
    {
    }

    public function index()
    {
        // Copy before overlaying: cachedAll() may hand back the cached
        // instance itself (array cache driver), which other code reads raw.
        $settings = collect(Setting::cachedAll()->all());

        // Effective evaluation window state (schedule-aware) — consumers such as
        // the dashboard CTA read this key without knowing about schedules.
        $settings->put('evaluation_status', $this->schedules->globalEffectiveStatus());

        return response()->json($settings);
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
