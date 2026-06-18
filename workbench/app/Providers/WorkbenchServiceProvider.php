<?php

namespace Workbench\App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Watchtower\Watchtower;
use Workbench\App\Console\DemoSeedCommand;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Allow viewing the dashboard in the preview (no auth setup needed).
        Watchtower::auth(fn () => true);

        // Register a few scheduled tasks so the Schedule tab has real content.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('inspire')->hourly()->description('Send daily inspiration');
            $schedule->command('queue:prune-batches')->daily()->description('Prune old job batches');
            $schedule->command('watchtower:prune')->dailyAt('03:00')->description('Prune Watchtower records');
            $schedule->call(fn () => true)->everyFiveMinutes()->name('cache:warm')->description('Warm application caches');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([DemoSeedCommand::class]);
        }
    }
}
