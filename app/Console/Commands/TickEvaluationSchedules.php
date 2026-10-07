<?php

namespace App\Console\Commands;

use App\Services\EvaluationScheduleService;
use Illuminate\Console\Command;

/**
 * Fires open/close notifications when an evaluation window boundary passes.
 *
 * Scheduled every minute (bootstrap/app.php). Gating itself is evaluated at
 * request time, so this command only affects notifications — if the scheduler
 * is not running, windows still open and close on time and students are still
 * notified the next time a student loads their evaluatee list (which also
 * runs a sync).
 */
class TickEvaluationSchedules extends Command
{
    protected $signature = 'evaluation-schedules:tick';

    protected $description = 'Detect evaluation window transitions and notify the affected students';

    public function handle(EvaluationScheduleService $schedules): int
    {
        $schedules->syncNotifications();

        return self::SUCCESS;
    }
}
