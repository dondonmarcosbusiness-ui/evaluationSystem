<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EvaluationSchedule;
use App\Models\Setting;
use App\Services\EvaluationScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for evaluation windows (per department, plus one institution-wide
 * default row). Gating for students is done by EvaluationScheduleService at
 * request time; every mutation here only records the desired configuration and
 * then syncs the open/close notification snapshot.
 */
class EvaluationScheduleController extends Controller
{
    public function __construct(protected EvaluationScheduleService $schedules)
    {
    }

    public function index()
    {
        $rows = EvaluationSchedule::query()->orderByRaw('department IS NULL DESC')->orderBy('department')->get();

        return response()->json([
            'schedules' => $rows->map(fn (EvaluationSchedule $row) => $this->present($row))->values(),
            'departments' => $this->allowedDepartments($rows),
            'global_status' => $this->schedules->globalEffectiveStatus(),
            // True while no rows exist — the UI uses it to show the "nothing
            // scheduled yet, everything closed" notice and the create button.
            'legacy_mode' => $rows->isEmpty(),
        ]);
    }

    public function status()
    {
        return response()->json([
            'status' => $this->schedules->globalEffectiveStatus(),
        ]);
    }

    public function store(Request $request)
    {
        $rows = EvaluationSchedule::query()->get();
        $data = $this->validateSchedule($request, $rows);

        if ($data['department'] === null && $rows->contains(fn (EvaluationSchedule $row) => $row->department === null)) {
            return response()->json(['message' => 'An "All departments" schedule already exists. Edit it instead.'], 422);
        }
        if ($data['department'] !== null && $rows->contains(fn (EvaluationSchedule $row) => $row->department === $data['department'])) {
            return response()->json(['message' => 'A schedule already exists for this department. Edit it instead.'], 422);
        }

        $schedule = EvaluationSchedule::create($data);

        $this->schedules->syncNotifications();

        return response()->json([
            'message' => 'Schedule created successfully',
            'schedule' => $this->present($schedule),
            'global_status' => $this->schedules->globalEffectiveStatus(),
        ], 201);
    }

    public function update(Request $request, EvaluationSchedule $schedule)
    {
        $rows = EvaluationSchedule::query()->where('id', '!=', $schedule->id)->get();
        $data = $this->validateSchedule($request, $rows);

        if ($data['department'] === null && $rows->contains(fn (EvaluationSchedule $row) => $row->department === null)) {
            return response()->json(['message' => 'An "All departments" schedule already exists.'], 422);
        }
        if ($data['department'] !== null && $rows->contains(fn (EvaluationSchedule $row) => $row->department === $data['department'])) {
            return response()->json(['message' => 'A schedule already exists for this department.'], 422);
        }

        $schedule->update($data);

        $this->schedules->syncNotifications();

        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => $this->present($schedule),
            'global_status' => $this->schedules->globalEffectiveStatus(),
        ]);
    }

    public function destroy(EvaluationSchedule $schedule)
    {
        $department = $schedule->department;
        $schedule->delete();

        $message = $department === null
            ? 'Default schedule deleted. Departments without their own schedule are now closed.'
            : 'Schedule deleted. This department now follows the default schedule.';

        if (!EvaluationSchedule::query()->exists()) {
            // An empty table is fail-closed. Keep the legacy `evaluation_status`
            // key pinned to the same value so any leftover reader (and the
            // notification sync below) agrees the window is shut.
            Setting::updateOrCreate(
                ['key' => 'evaluation_status'],
                ['value' => EvaluationScheduleService::CLOSED]
            );
            Setting::forgetCache();

            $message = 'Schedule deleted. No schedules remain — the evaluation window is now closed.';
        }

        $this->schedules->syncNotifications();

        return response()->json([
            'message' => $message,
            'global_status' => $this->schedules->globalEffectiveStatus(),
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, EvaluationSchedule> $existing
     * @return array{department: ?string, starts_at: ?string, ends_at: ?string, status: string}
     */
    private function validateSchedule(Request $request, $existing): array
    {
        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:60', Rule::in($this->allowedDepartments($existing))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(EvaluationSchedule::STATUSES)],
        ]);

        $data['department'] = isset($data['department']) && trim($data['department']) !== ''
            ? trim($data['department'])
            : null;

        if (
            isset($data['starts_at'], $data['ends_at'])
            && strtotime((string) $data['ends_at']) < strtotime((string) $data['starts_at'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => 'The end date must be after the start date.',
            ]);
        }

        if (
            $data['status'] === EvaluationSchedule::STATUS_SCHEDULED
            && empty($data['starts_at'])
            && empty($data['ends_at'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'starts_at' => 'A scheduled window needs a start and/or end date. Choose Open or Closed for a manual window.',
            ]);
        }

        return [
            'department' => $data['department'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => $data['status'],
        ];
    }

    /**
     * Departments an admin may schedule: derived from the Course List plus the
     * system-wide "General Education" bucket (same convention as Faculty and
     * Assignment Management), plus any department already on a schedule row so
     * existing rows stay editable even if their courses are removed.
     *
     * @param \Illuminate\Support\Collection<int, EvaluationSchedule> $existing
     * @return array<int, string>
     */
    private function allowedDepartments($existing): array
    {
        $departments = Course::query()->pluck('department')
            ->concat($existing->pluck('department'))
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => trim($d))
            ->unique();

        if (!$departments->contains('General Education')) {
            $departments->push('General Education');
        }

        return $departments->sort()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EvaluationSchedule $row): array
    {
        return [
            'id' => $row->id,
            'department' => $row->department,
            'is_global' => $row->department === null,
            'starts_at' => $row->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $row->ends_at?->format('Y-m-d\TH:i'),
            'status' => $row->status,
            'effective_status' => $this->schedules->rowStatus($row),
        ];
    }
}
