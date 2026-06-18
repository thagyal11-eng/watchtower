<?php

namespace Watchtower\Models;

/**
 * @property string|null $uuid
 * @property string $name
 * @property string $status
 * @property string|null $queue
 */
class JobRecord extends WatchtowerModel
{
    protected string $baseTable = 'job_records';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'exception_id' => 'integer',
    ];

    public function exception()
    {
        return $this->belongsTo(ExceptionRecord::class, 'exception_id');
    }
}
