<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Watchtower\Models\JobRecord;
use Watchtower\Models\ScheduleRun;
use Watchtower\Notifications\Channels\GenericWebhookChannel;
use Watchtower\Notifications\Channels\SlackWebhookChannel;
use Watchtower\Notifications\WatchtowerAlert;
use Watchtower\Support\AlertManager;
use Watchtower\Support\TaskKey;

beforeEach(function () {
    Notification::fake();
});

it('does nothing and returns 0 when alerts are disabled', function () {
    config()->set('watchtower.alerts.enabled', false);

    $sent = app(AlertManager::class)->run();

    expect($sent)->toBe(0);
    Notification::assertNothingSent();
});

it('sends an alert when a scheduled task is missed', function () {
    config()->set('watchtower.alerts.enabled', true);
    // Only the missed check should fire.
    config()->set('watchtower.alerts.on', [
        'schedule_missed' => true,
        'schedule_failed' => false,
        'failed_jobs_threshold' => false,
    ]);

    // A channel must be configured for the notification to have any "via".
    config()->set('watchtower.alerts.channels', ['mail' => ['ops@example.com']]);

    $event = app(Schedule::class)->command('inspire')->hourly();

    ScheduleRun::create([
        'task_key' => TaskKey::for($event),
        'command' => 'inspire',
        'expression' => '0 * * * *',
        'started_at' => now()->subDays(2),
        'finished_at' => now()->subDays(2),
        'status' => 'success',
    ]);

    $sent = app(AlertManager::class)->run();

    expect($sent)->toBe(1);
    Notification::assertSentOnDemand(WatchtowerAlert::class);
});

it('sends an alert when the failed-job threshold is crossed', function () {
    config()->set('watchtower.alerts.enabled', true);
    // Only the failed-jobs check should fire.
    config()->set('watchtower.alerts.on', [
        'schedule_missed' => false,
        'schedule_failed' => false,
        'failed_jobs_threshold' => true,
    ]);
    config()->set('watchtower.alerts.failed_jobs.threshold', 2);
    config()->set('watchtower.alerts.failed_jobs.window_minutes', 60);
    config()->set('watchtower.alerts.channels', ['mail' => ['ops@example.com']]);

    foreach (range(1, 3) as $i) {
        JobRecord::create([
            'name' => "App\\Jobs\\Failing{$i}",
            'status' => 'failed',
            'finished_at' => now(),
        ]);
    }

    $sent = app(AlertManager::class)->run();

    expect($sent)->toBe(1);
    Notification::assertSentOnDemand(WatchtowerAlert::class);
});

it('does not alert when the failed-job count is below threshold', function () {
    config()->set('watchtower.alerts.enabled', true);
    config()->set('watchtower.alerts.on', [
        'schedule_missed' => false,
        'schedule_failed' => false,
        'failed_jobs_threshold' => true,
    ]);
    config()->set('watchtower.alerts.failed_jobs.threshold', 2);
    config()->set('watchtower.alerts.failed_jobs.window_minutes', 60);

    JobRecord::create([
        'name' => 'App\\Jobs\\Failing',
        'status' => 'failed',
        'finished_at' => now(),
    ]);

    $sent = app(AlertManager::class)->run();

    expect($sent)->toBe(0);
    Notification::assertNothingSent();
});

it('monitor command succeeds and sends nothing when disabled', function () {
    config()->set('watchtower.alerts.enabled', false);

    $this->artisan('watchtower:monitor')->assertSuccessful();

    Notification::assertNothingSent();
});

it('monitor command sends an alert when enabled with a triggering condition', function () {
    config()->set('watchtower.alerts.enabled', true);
    config()->set('watchtower.alerts.on', [
        'schedule_missed' => false,
        'schedule_failed' => false,
        'failed_jobs_threshold' => true,
    ]);
    config()->set('watchtower.alerts.failed_jobs.threshold', 1);
    config()->set('watchtower.alerts.failed_jobs.window_minutes', 60);
    config()->set('watchtower.alerts.channels', ['mail' => ['ops@example.com']]);

    JobRecord::create([
        'name' => 'App\\Jobs\\Failing',
        'status' => 'failed',
        'finished_at' => now(),
    ]);

    $this->artisan('watchtower:monitor')->assertSuccessful();

    Notification::assertSentOnDemand(WatchtowerAlert::class);
});

it('via() returns only the slack channel when only slack is configured', function () {
    config()->set('watchtower.alerts.channels', [
        'slack' => 'https://hooks.slack.test/xyz',
        'webhook' => null,
        'mail' => [],
    ]);

    $channels = (new WatchtowerAlert('t', 'b'))->via(new \Illuminate\Notifications\AnonymousNotifiable);

    expect($channels)->toContain(SlackWebhookChannel::class);
    expect($channels)->not->toContain(GenericWebhookChannel::class);
    expect($channels)->not->toContain('mail');
});

it('via() includes mail when a mail recipient array is configured', function () {
    config()->set('watchtower.alerts.channels', [
        'slack' => null,
        'webhook' => null,
        'mail' => ['ops@example.com'],
    ]);

    $channels = (new WatchtowerAlert('t', 'b'))->via(new \Illuminate\Notifications\AnonymousNotifiable);

    expect($channels)->toContain('mail');
    expect($channels)->not->toContain(SlackWebhookChannel::class);
});
