<?php

namespace Watchtower\Models;

/**
 * @property string $task_key
 * @property string $command
 * @property string|null $expression
 * @property string $status
 */
class ScheduleRun extends WatchtowerModel
{
    protected string $baseTable = 'schedule_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];
}
