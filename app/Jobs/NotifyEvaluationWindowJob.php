<?php

namespace App\Jobs;

use App\Mail\EvaluationWindowNotice;
use App\Models\Setting;
use App\Models\User;
use App\Services\EvaluateeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the students who actually have evaluatees in the departments whose
 * evaluation window just opened or closed.
 *
 * Audience is resolved through EvaluateeService (the same source of truth the
 * student-facing evaluatee list uses), so a student is only emailed when they
 * have something to evaluate — and at most once per dispatch.
 */
class NotifyEvaluationWindowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int, string> $departments departments whose window changed
     * @param string $event 'open' or 'closed'
     */
    public function __construct(
        public array $departments,
        public string $event,
    ) {
    }

    public function handle(): void
    {
        $departments = array_values(array_unique(array_filter($this->departments, fn ($d) => is_string($d) && $d !== '')));

        if ($departments === [] || !in_array($this->event, ['open', 'closed'], true)) {
            return;
        }

        $settings = Setting::cachedAll();
        $semester = $settings->get('active_semester', 'N/A');
        $academicYear = $settings->get('active_academic_year', 'N/A');

        $evaluatees = app(EvaluateeService::class);

        $students = User::where('role', 'student')
            ->where('is_active', true)
            ->get();

        $targets = [];
        foreach ($students as $student) {
            try {
                $rows = $evaluatees->build($student, $semester, $academicYear);
            } catch (\Throwable $e) {
                Log::warning("Skipping student {$student->id} for window notification: " . $e->getMessage());
                continue;
            }

            foreach ($rows as $row) {
                if (in_array($row['department'] ?? null, $departments, true)) {
                    $targets[$student->id] = $student;
                    break;
                }
            }
        }

        Log::info("Evaluation window {$this->event} notification for departments [" . implode(', ', $departments) . '] → ' . count($targets) . ' students.');

        foreach ($targets as $student) {
            try {
                // Priority: use the linked Google email if available, otherwise the primary email.
                $targetEmail = $student->is_google_linked ? $student->google_email : $student->email;

                if ($targetEmail) {
                    Mail::to($targetEmail)->send(new EvaluationWindowNotice(
                        $student->name,
                        $this->event,
                        $departments,
                        $semester,
                        $academicYear
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to notify student ID {$student->id} of evaluation window {$this->event}: " . $e->getMessage());
            }
        }
    }
}
