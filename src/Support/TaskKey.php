<?php

namespace Watchtower\Support;

use Illuminate\Console\Scheduling\Event as SchedulingEvent;

/**
 * Produces a stable identifier for a scheduled task so runs of the same task
 * group together across the schedule definition and the recorded history.
 */
class TaskKey
{
    public static function for(SchedulingEvent $task): string
    {
        $command = $task->command ?? $task->getSummaryForDisplay() ?? '';
        $expression = $task->expression ?? '';

        return self::hash($command, $expression);
    }

    public static function hash(string $command, string $expression): string
    {
        return hash('sha256', $command.'|'.$expression);
    }
}
