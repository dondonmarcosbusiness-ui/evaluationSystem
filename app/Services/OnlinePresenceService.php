<?php

namespace App\Services;

use App\Models\StudentOnlinePeak;
use App\Models\User;

/**
 * Student presence snapshots and the daily "peak online" high-water mark.
 *
 * Presence is derived from Sanctum token activity (personal_access_tokens
 * last_used_at) — a live snapshot only, so the daily peak must be sampled
 * over time: DashboardController records it on every status fetch, and the
 * online-peak:sample scheduled command records it every minute.
 */
class OnlinePresenceService
{
    /** Minutes of token activity that count as "online" (matches the dashboard default). */
    public const ONLINE_WINDOW_MINUTES = 5;

    /** Distinct active student accounts whose API token was used within the window. */
    public function currentOnlineCount(?int $minutes = null): int
    {
        $minutes = min(max($minutes ?? self::ONLINE_WINDOW_MINUTES, 1), 60);
        $cutoff = now()->subMinutes($minutes);

        return User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->whereHas('student')
            ->whereHas('tokens', fn ($q) => $q->where('last_used_at', '>=', $cutoff))
            ->count();
    }

    /**
     * Raise today's peak to at least $count (never lowers it) and return it.
     * Date is the app timezone day — Asia/Manila by default.
     */
    public function recordPeak(int $count): int
    {
        $peak = StudentOnlinePeak::query()
            ->whereDate('date', now()->toDateString())
            ->first();

        if ($peak === null) {
            $peak = StudentOnlinePeak::create([
                'date' => now()->toDateString(),
                'peak' => max($count, 0),
            ]);
        } elseif ($count > $peak->peak) {
            $peak->peak = $count;
            $peak->save();
        }

        return (int) $peak->peak;
    }

    /** Today's recorded peak (0 when nothing has been sampled yet). */
    public function peakToday(): int
    {
        return (int) StudentOnlinePeak::query()
            ->whereDate('date', now()->toDateString())
            ->value('peak');
    }
}
