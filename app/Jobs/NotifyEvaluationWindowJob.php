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
 * Emails the students whose evaluation window just opened or closed.
 *
 * Audience mirrors the dashboard CTA (SettingController::effectiveStatusFor):
 * the departments of the student's built evaluatees decide, falling back to
 * their own course's department when no evaluatees are built yet (no
 * assignments, or the active period isn't configured). Without that fallback
 * students without assigned instructors were never emailed — the old
 * evaluation-status toggle notified every active student instead.
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
        // The batch runs inside the request that noticed the transition (or
        // the scheduler tick), and Windows counts wall-clock time — including
        // the SMTP round-trips below — against max_execution_time. Without
        // this the request was killed ~60s in, mid-batch, and the students
        // after the cut-off were never notified. ignore_user_abort keeps the
        // loop alive if the browser or tunnel drops while we're still sending.
        set_time_limit(300);
        ignore_user_abort(true);

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

            // Same resolution order as the dashboard CTA: built evaluatee
            // departments decide; with none built yet, the student's own
            // course department does — so students without assignments are
            // still told when their window opens or closes.
            $relevant = [];
            foreach ($rows as $row) {
                $department = $row['department'] ?? null;
                if (is_string($department) && trim($department) !== '') {
                    $relevant[trim($department)] = true;
                }
            }
            if ($relevant === []) {
                foreach ($evaluatees->homeDepartments($student) as $department) {
                    $relevant[$department] = true;
                }
            }

            if (array_intersect(array_keys($relevant), $departments) !== []) {
                $targets[$student->id] = $student;
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

        // A completion line lets us tell a finished batch apart from one that
        // was killed mid-send (no completion line + earlier start line = kill).
        Log::info("Evaluation window {$this->event} notification finished: " . count($targets) . ' targeted.');
    }
}
