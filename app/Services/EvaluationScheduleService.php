<?php

namespace App\Services;

use App\Jobs\NotifyEvaluationWindowJob;
use App\Models\Course;
use App\Models\EvaluationSchedule;
use App\Models\Faculty;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Resolves "is the evaluation window open for department X right now?".
 *
 * Resolution order for a department:
 *   1. the department's own schedule row
 *   2. the institution-wide default row (department = NULL)
 *   3. 'closed'
 * While the evaluation_schedules table is empty the legacy `evaluation_status`
 * setting is used instead, so installs (and tests) that still rely on the old
 * global switch behave exactly as before.
 *
 * Gating is evaluated at request time — no scheduler is required for
 * correctness. The scheduler (evaluation-schedules:tick) only exists to fire
 * open/close notifications at window boundaries; syncNotifications() diffs a
 * stored per-department snapshot to decide who to email.
 */
class EvaluationScheduleService
{
    public const SNAPSHOT_KEY = 'evaluation_schedule_snapshot';

    public const OPEN = 'open';
    public const CLOSED = 'closed';

    /**
     * Resolver for the current process: captures the rows once so a list of
     * evaluatees can be filtered without a query per row.
     *
     * @return \Closure(?string): string
     */
    public function resolver(): \Closure
    {
        $rows = EvaluationSchedule::query()->get();

        if ($rows->isEmpty()) {
            $legacy = $this->legacyStatus();

            return static fn (?string $department): string => $legacy;
        }

        $map = [];
        $globalStatus = self::CLOSED;

        foreach ($rows as $row) {
            $status = $this->rowStatus($row);
            if ($row->department === null) {
                $globalStatus = $status;
            } else {
                $map[$row->department] = $status;
            }
        }

        return static function (?string $department) use ($map, $globalStatus): string {
            if ($department !== null && $department !== '' && isset($map[$department])) {
                return $map[$department];
            }

            return $globalStatus;
        };
    }

    /** Is the window open for the given evaluatee department right now? */
    public function isOpenFor(?string $department): bool
    {
        $resolve = $this->resolver();

        return $resolve($this->normalize($department)) === self::OPEN;
    }

    /**
     * Institution-wide effective status: open as soon as any department (or
     * the default row) is open. Feeds the dashboard CTA via /settings.
     */
    public function globalEffectiveStatus(): string
    {
        $rows = EvaluationSchedule::query()->get();

        if ($rows->isEmpty()) {
            return $this->legacyStatus();
        }

        foreach ($rows as $row) {
            if ($this->rowStatus($row) === self::OPEN) {
                return self::OPEN;
            }
        }

        return self::CLOSED;
    }

    /**
     * Status of every department the system knows about. This is the universe
     * used to diff window transitions for notifications.
     *
     * @return array<string, string>
     */
    public function computeUniverseStatuses(): array
    {
        $resolve = $this->resolver();

        $departments = Course::query()->pluck('department')
            ->concat(Faculty::query()->pluck('department'))
            ->concat(EvaluationSchedule::query()->pluck('department'))
            ->push('General Education')
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => trim($d))
            ->unique()
            ->values();

        $statuses = [];
        foreach ($departments as $department) {
            $statuses[$department] = $resolve($department);
        }

        return $statuses;
    }

    /**
     * Detect window transitions and notify the affected students.
     *
     * Compares the stored per-department snapshot with a freshly computed one.
     * Called after every admin mutation, by the minute scheduler, and (as a
     * cron-independent safety net) when a student loads their evaluatee list.
     * Failures are logged and never bubble up to the caller.
     */
    public function syncNotifications(): void
    {
        try {
            $after = $this->computeUniverseStatuses();
            $before = $this->snapshot();

            if ($before === null) {
                // First run (snapshot lost or never seeded): record the current
                // state silently instead of blasting everyone.
                $this->saveSnapshot($after);

                return;
            }

            $opened = [];
            $closed = [];
            foreach ($after as $department => $status) {
                if (!array_key_exists($department, $before) || $before[$department] === $status) {
                    continue;
                }
                if ($status === self::OPEN) {
                    $opened[] = $department;
                } else {
                    $closed[] = $department;
                }
            }

            if ($before != $after) {
                $this->saveSnapshot($after);
            }

            if ($opened !== []) {
                NotifyEvaluationWindowJob::dispatch($opened, self::OPEN);
            }
            if ($closed !== []) {
                NotifyEvaluationWindowJob::dispatch($closed, self::CLOSED);
            }
        } catch (\Throwable $e) {
            Log::warning('Evaluation schedule notification sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Effective status of a single schedule row (manual overrides win over
     * the dates).
     */
    public function rowStatus(EvaluationSchedule $row): string
    {
        if ($row->status === EvaluationSchedule::STATUS_OPEN) {
            return self::OPEN;
        }
        if ($row->status === EvaluationSchedule::STATUS_CLOSED) {
            return self::CLOSED;
        }

        $now = now();
        if ($row->starts_at !== null && $now->lt($row->starts_at)) {
            return self::CLOSED;
        }
        if ($row->ends_at !== null && $now->gt($row->ends_at)) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    /**
     * Legacy global switch — only consulted while no schedule rows exist.
     * Fail-closed: anything other than an explicit 'open' counts as closed.
     */
    private function legacyStatus(): string
    {
        $value = Setting::cachedAll()->get('evaluation_status');

        return is_string($value) && $value === self::OPEN ? self::OPEN : self::CLOSED;
    }

    /** @return array<string, mixed>|null */
    private function snapshot(): ?array
    {
        $value = Setting::query()->where('key', self::SNAPSHOT_KEY)->value('value');

        return is_array($value) ? $value : null;
    }

    /** @param array<string, string> $statuses */
    private function saveSnapshot(array $statuses): void
    {
        Setting::updateOrCreate(
            ['key' => self::SNAPSHOT_KEY],
            ['value' => $statuses]
        );
    }

    private function normalize(?string $department): ?string
    {
        if ($department === null) {
            return null;
        }
        $department = trim($department);

        return $department === '' ? null : $department;
    }
}
