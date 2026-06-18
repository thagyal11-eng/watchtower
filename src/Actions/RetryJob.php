<?php

namespace Watchtower\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * Retry a single failed job by id using Laravel's native mechanism, so it is
 * correct across every queue driver.
 */
class RetryJob
{
    public function execute(string $id): int
    {
        return Artisan::call('queue:retry', ['id' => [$id]]);
    }
}
