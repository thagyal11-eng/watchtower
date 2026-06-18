<?php

namespace Watchtower\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * Forget a single failed job via Laravel's native queue:forget.
 */
class DeleteFailedJob
{
    public function execute(string $id): int
    {
        return Artisan::call('queue:forget', ['id' => $id]);
    }
}
