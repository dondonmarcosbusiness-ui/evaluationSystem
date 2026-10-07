<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A restored backup can revert the migrations table while the table
        // itself survives, so guard against re-creating it.
        if (Schema::hasTable('evaluation_schedules')) {
            return;
        }

        Schema::create('evaluation_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // NULL = "All departments" default row. A department with its own
            // row overrides the default; departments without one fall back to
            // it (and to 'closed' when no default row exists).
            $table->string('department')->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // scheduled = governed by the dates, open/closed = manual override.
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });

        // Seed the notification snapshot from the legacy switch so upgrading
        // never triggers a spurious "window opened" email blast. The snapshot
        // is only used to diff transitions — never for gating.
        $legacy = \App\Models\Setting::where('key', 'evaluation_status')->value('value');
        $legacy = is_string($legacy) ? $legacy : 'closed';
        $status = $legacy === 'open' ? 'open' : 'closed';

        $departments = \App\Models\Course::query()->pluck('department')
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => trim($d))
            ->unique()
            ->values();

        $facultyDepartments = \App\Models\Faculty::query()->pluck('department')
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => trim($d))
            ->unique()
            ->values();

        $all = $departments->concat($facultyDepartments)->push('General Education')->unique()->values();

        $snapshot = [];
        foreach ($all as $department) {
            $snapshot[$department] = $status;
        }

        \App\Models\Setting::updateOrCreate(
            ['key' => 'evaluation_schedule_snapshot'],
            ['value' => $snapshot]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_schedules');
    }
};
