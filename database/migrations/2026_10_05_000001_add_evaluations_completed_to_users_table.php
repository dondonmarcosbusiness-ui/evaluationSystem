<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\EvaluateeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('evaluations_completed_at')->nullable();
            $table->string('evaluations_completed_semester')->nullable();
            $table->string('evaluations_completed_academic_year')->nullable();
        });

        $this->backfillCompletedFlag();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'evaluations_completed_at',
                'evaluations_completed_semester',
                'evaluations_completed_academic_year',
            ]);
        });
    }

    /**
     * Existing students never went through the submission-time write, so
     * recompute the flag once for the active period. Skipped when no period is
     * configured yet — on a fresh install settings are seeded after migrating.
     */
    private function backfillCompletedFlag(): void
    {
        if (!Schema::hasTable('students')) {
            return;
        }

        $settings = Setting::cachedAll();
        $semester = $settings->get('active_semester');
        $academicYear = $settings->get('active_academic_year');

        if (!is_string($semester) || trim($semester) === '') {
            return;
        }
        if (!is_string($academicYear) || trim($academicYear) === '') {
            return;
        }

        $service = app(EvaluateeService::class);

        User::query()
            ->where('role', 'student')
            ->whereHas('student')
            ->each(function (User $student) use ($service, $semester, $academicYear) {
                $service->markCompleted($student, $semester, $academicYear);
            });
    }
};
