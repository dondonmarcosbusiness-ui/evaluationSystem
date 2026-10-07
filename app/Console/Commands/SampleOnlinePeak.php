<?php

namespace App\Console\Commands;

use App\Services\OnlinePresenceService;
use Illuminate\Console\Command;

/**
 * Samples the current online-student count into the daily peak row.
 * Scheduled every minute (bootstrap/app.php) so the dashboard's peak
 * stays accurate even while nobody has the dashboard open.
 */
class SampleOnlinePeak extends Command
{
    protected $signature = 'online-peak:sample';

    protected $description = 'Record the current online student count into today\'s peak high-water mark';

    public function handle(OnlinePresenceService $presence): int
    {
        $count = $presence->currentOnlineCount();
        $peak = $presence->recordPeak($count);

        $this->info("Online: {$count} · today's peak: {$peak}");

        return self::SUCCESS;
    }
}
