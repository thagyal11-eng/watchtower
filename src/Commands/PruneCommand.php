<?php

namespace Watchtower\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Watchtower\Models\ExceptionRecord;
use Watchtower\Models\JobRecord;
use Watchtower\Models\ScheduleRun;

class PruneCommand extends Command
{
    protected $signature = 'watchtower:prune
                            {--hours= : Override every retention window with this many hours}';

    protected $description = 'Prune Watchtower records older than the configured retention windows';

    public function handle(): int
    {
        $hoursOverride = $this->option('hours');

        $deleted = 0;
        $deleted += $this->pruneScheduleRuns($hoursOverride);
        $deleted += $this->pruneJobRecords($hoursOverride);
        $deleted += $this->pruneExceptions($hoursOverride);

        $this->components->info("Watchtower pruned {$deleted} record(s).");

        return self::SUCCESS;
    }

    protected function pruneScheduleRuns($hoursOverride): int
    {
        $before = $this->cutoff($hoursOverride, config('watchtower.retention.schedule', 30));

        return ScheduleRun::query()
            ->where('started_at', '<', $before)
            ->delete();
    }

    protected function pruneJobRecords($hoursOverride): int
    {
        $before = $this->cutoff($hoursOverride, config('watchtower.retention.queue', 7));

        // Keep rows that have no completion timestamp yet (still in flight).
        return JobRecord::query()
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $before)
            ->delete();
    }

    protected function pruneExceptions($hoursOverride): int
    {
        $before = $this->cutoff($hoursOverride, config('watchtower.retention.exceptions', 30));

        // Only prune exceptions whose latest occurrence is old.
        return ExceptionRecord::query()
            ->where('last_seen_at', '<', $before)
            ->delete();
    }

    protected function cutoff($hoursOverride, int $days): Carbon
    {
        if ($hoursOverride !== null) {
            return Carbon::now()->subHours((int) $hoursOverride);
        }

        return Carbon::now()->subDays($days);
    }
}
