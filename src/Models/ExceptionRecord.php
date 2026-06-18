<?php

namespace Watchtower\Models;

/**
 * @property string $fingerprint
 * @property string $class
 * @property int $count
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class ExceptionRecord extends WatchtowerModel
{
    protected string $baseTable = 'exceptions';

    protected $guarded = [];

    protected $casts = [
        'line' => 'integer',
        'count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function getIsResolvedAttribute(): bool
    {
        return $this->resolved_at !== null;
    }
}
