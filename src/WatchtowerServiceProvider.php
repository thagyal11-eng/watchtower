<?php

namespace Watchtower;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Watchtower\Commands\InstallCommand;
use Watchtower\Commands\MonitorCommand;
use Watchtower\Commands\PruneCommand;
use Watchtower\Listeners\ExceptionListener;
use Watchtower\Listeners\QueueListener;
use Watchtower\Listeners\ScheduleListener;
use Watchtower\Storage\MetricRepository;

class WatchtowerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/watchtower.php', 'watchtower');

        // The repository is the single funnel for every write/read. Bound as a
        // singleton so deferred (after-response) writes accumulate in one place.
        $this->app->singleton(MetricRepository::class, function ($app) {
            return new MetricRepository($app['config']->get('watchtower'));
        });

        $this->app->singleton('watchtower', fn ($app) => $app->make(MetricRepository::class));
    }

    /**
     * Bootstrap the package.
     */
    public function boot(): void
    {
        $this->registerGate();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();

        if (config('watchtower.enabled', true)) {
            $this->registerListeners();
            $this->registerExceptionCapture();
        }
    }

    /**
     * The default gate allows only the local environment, mirroring
     * Horizon/Telescope. Override it via Watchtower::auth() or by redefining
     * the "viewWatchtower" gate in your own service provider.
     */
    protected function registerGate(): void
    {
        Gate::define('viewWatchtower', function ($user = null) {
            return $this->app->environment('local');
        });
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'domain' => config('watchtower.domain'),
            'prefix' => config('watchtower.path', 'watchtower'),
            'middleware' => array_merge(
                (array) config('watchtower.middleware', ['web']),
                [\Watchtower\Http\Middleware\Authorize::class]
            ),
            'as' => 'watchtower.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'watchtower');
    }

    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/watchtower.php' => config_path('watchtower.php'),
        ], 'watchtower-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'watchtower-migrations');

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/watchtower'),
        ], 'watchtower-assets');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PruneCommand::class,
                MonitorCommand::class,
            ]);
        }
    }

    /**
     * Wire scheduler + queue events to their listeners. Exception capture is
     * registered separately because it hooks the host's handler, not events.
     */
    protected function registerListeners(): void
    {
        $recording = config('watchtower.recording', []);

        if ($recording['schedule'] ?? true) {
            Event::listen(ScheduledTaskStarting::class, [ScheduleListener::class, 'starting']);
            Event::listen(ScheduledTaskFinished::class, [ScheduleListener::class, 'finished']);
            Event::listen(ScheduledTaskFailed::class, [ScheduleListener::class, 'failed']);
            Event::listen(ScheduledTaskSkipped::class, [ScheduleListener::class, 'skipped']);
        }

        if ($recording['queue'] ?? true) {
            // JobQueued only exists / fires on some drivers — guard it.
            if (class_exists(JobQueued::class)) {
                Event::listen(JobQueued::class, [QueueListener::class, 'queued']);
            }
            Event::listen(JobProcessing::class, [QueueListener::class, 'processing']);
            Event::listen(JobProcessed::class, [QueueListener::class, 'processed']);
            Event::listen(JobFailed::class, [QueueListener::class, 'failed']);
            if (class_exists(JobReleasedAfterException::class)) {
                Event::listen(JobReleasedAfterException::class, [QueueListener::class, 'released']);
            }
        }
    }

    /**
     * Register a reportable callback on the host exception handler. It records
     * the exception and returns nothing falsy that would alter app behaviour —
     * Laravel continues its normal reporting/rendering after this runs.
     */
    protected function registerExceptionCapture(): void
    {
        if (! (config('watchtower.recording.exceptions', true))) {
            return;
        }

        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    app(ExceptionListener::class)->handle($e);
                });
            }
        });
    }
}
