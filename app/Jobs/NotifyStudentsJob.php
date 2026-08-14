<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Setting;
use App\Mail\EvaluationAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotifyStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $settings = Setting::cachedAll();
        $semester = $settings->get('active_semester', 'N/A');
        $academicYear = $settings->get('active_academic_year', 'N/A');

        // Fetch all active students
        $students = User::where('role', 'student')
            ->where('is_active', true)
            ->get();

        Log::info("Starting evaluation notification for " . $students->count() . " students.");

        foreach ($students as $student) {
            try {
                // Priority: Use the linked Google email if available, otherwise the primary email.
                $targetEmail = $student->is_google_linked ? $student->google_email : $student->email;

                if ($targetEmail) {
                    Mail::to($targetEmail)->send(new EvaluationAnnouncement(
                        $student->name,
                        $semester,
                        $academicYear
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to notify student ID {$student->id}: " . $e->getMessage());
            }
        }

        Log::info("Finished evaluation notification.");
    }
}
