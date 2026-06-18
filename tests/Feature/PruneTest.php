<?php

use Watchtower\Models\ExceptionRecord;
use Watchtower\Models\JobRecord;
use Watchtower\Models\ScheduleRun;

it('prunes only records older than the retention windows', function () {
    config()->set('watchtower.retention.schedule', 30);
    config()->set('watchtower.retention.queue', 7);
    config()->set('watchtower.retention.exceptions', 30);

    // Old (should be pruned)
    ScheduleRun::create(['task_key' => 'k', 'command' => 'c', 'started_at' => now()->subDays(40), 'status' => 'success']);
    JobRecord::create(['name' => 'J', 'status' => 'processed', 'finished_at' => now()->subDays(10)]);
    ExceptionRecord::create(['fingerprint' => 'old', 'class' => 'E', 'count' => 1, 'last_seen_at' => now()->subDays(40)]);

    // Fresh (should survive)
    ScheduleRun::create(['task_key' => 'k', 'command' => 'c', 'started_at' => now()->subDay(), 'status' => 'success']);
    JobRecord::create(['name' => 'J', 'status' => 'processed', 'finished_at' => now()->subDay()]);
    ExceptionRecord::create(['fingerprint' => 'new', 'class' => 'E', 'count' => 1, 'last_seen_at' => now()->subDay()]);

    $this->artisan('watchtower:prune')->assertSuccessful();

    expect(ScheduleRun::count())->toBe(1);
    expect(JobRecord::count())->toBe(1);
    expect(ExceptionRecord::count())->toBe(1);
    expect(ExceptionRecord::first()->fingerprint)->toBe('new');
});

it('keeps in-flight job records (no finished_at) regardless of age', function () {
    JobRecord::create(['name' => 'J', 'status' => 'queued', 'queued_at' => now()->subDays(90)]);

    $this->artisan('watchtower:prune')->assertSuccessful();

    expect(JobRecord::count())->toBe(1);
});

it('supports an --hours override', function () {
    ScheduleRun::create(['task_key' => 'k', 'command' => 'c', 'started_at' => now()->subHours(5), 'status' => 'success']);
    ScheduleRun::create(['task_key' => 'k', 'command' => 'c', 'started_at' => now()->subHours(1), 'status' => 'success']);

    $this->artisan('watchtower:prune', ['--hours' => 3])->assertSuccessful();

    expect(ScheduleRun::count())->toBe(1);
});
