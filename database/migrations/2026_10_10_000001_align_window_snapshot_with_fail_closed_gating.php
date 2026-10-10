<?php

use App\Models\EvaluationSchedule;
use App\Models\Setting;
use App\Services\EvaluationScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluation_schedules') || !Schema::hasTable('settings')) {
            return;
        }

        // Gating is now fail-closed while no schedule row exists, so an install
        // upgraded from the old global switch resolves every department to
        // 'closed' even though the snapshot still records the stale legacy
        // 'open'. Re-baseline that snapshot here — only when nothing is
        // scheduled — so the upgrade itself never emits a one-off "window
        // closed" blast for a window nobody intentionally opened. Installs that
        // already have schedules keep their existing snapshot untouched.
        if (EvaluationSchedule::query()->exists()) {
            return;
        }

        Setting::updateOrCreate(
            ['key' => EvaluationScheduleService::SNAPSHOT_KEY],
            ['value' => app(EvaluationScheduleService::class)->computeUniverseStatuses()]
        );
    }

    public function down(): void
    {
        // The snapshot is only a diff baseline — there is nothing to restore.
    }
};
