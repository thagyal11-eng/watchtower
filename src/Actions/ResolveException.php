<?php

namespace Watchtower\Actions;

use Illuminate\Support\Carbon;
use Watchtower\Models\ExceptionRecord;

/**
 * Toggle the resolved state of a grouped exception.
 */
class ResolveException
{
    public function resolve(ExceptionRecord $record): ExceptionRecord
    {
        $record->forceFill(['resolved_at' => Carbon::now()])->save();

        return $record;
    }

    public function reopen(ExceptionRecord $record): ExceptionRecord
    {
        $record->forceFill(['resolved_at' => null])->save();

        return $record;
    }
}
