<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Watchtower\Listeners\ExceptionListener;
use Watchtower\Models\ExceptionRecord;
use Watchtower\Watchtower;

beforeEach(fn () => Watchtower::auth(fn () => true));
afterEach(fn () => Watchtower::$authUsing = null);

function capture(\Throwable $e): void
{
    app(ExceptionListener::class)->handle($e);
}

it('captures and groups exceptions by fingerprint', function () {
    $e = new RuntimeException('Something broke');

    capture($e);
    capture($e);

    expect(ExceptionRecord::count())->toBe(1);

    $record = ExceptionRecord::first();
    expect($record->count)->toBe(2);
    expect($record->class)->toBe(RuntimeException::class);
    expect($record->message)->toBe('Something broke');
    expect($record->first_seen_at)->not->toBeNull();
    expect($record->last_seen_at)->not->toBeNull();
});

it('keeps distinct exceptions in separate groups', function () {
    capture(new RuntimeException('a'));
    capture(new LogicException('b'));

    expect(ExceptionRecord::count())->toBe(2);
});

it('is wired into the host exception handler via a reportable callback', function () {
    app(ExceptionHandler::class)->report(new InvalidArgumentException('reported'));

    expect(ExceptionRecord::where('class', InvalidArgumentException::class)->exists())->toBeTrue();
});

it('resolves and reopens an exception', function () {
    capture(new RuntimeException('x'));
    $record = ExceptionRecord::first();

    $this->postJson("watchtower/api/exceptions/{$record->id}/resolve")->assertOk();
    expect($record->fresh()->resolved_at)->not->toBeNull();

    // Resolved exceptions drop out of the default (unresolved) view.
    $this->getJson('watchtower/api/exceptions')->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->postJson("watchtower/api/exceptions/{$record->id}/reopen")->assertOk();
    expect($record->fresh()->resolved_at)->toBeNull();
});

it('reopens a resolved exception when it recurs', function () {
    $e = new RuntimeException('flaky');
    capture($e);
    $record = ExceptionRecord::first();
    $record->update(['resolved_at' => now()]);

    capture($e);

    expect($record->fresh()->resolved_at)->toBeNull();
    expect($record->fresh()->count)->toBe(2);
});

it('respects the exceptions ignore list', function () {
    config()->set('watchtower.ignore.exceptions', [RuntimeException::class]);

    capture(new RuntimeException('ignored'));

    expect(ExceptionRecord::count())->toBe(0);
});

it('returns exceptions via the API sorted by frequency', function () {
    $a = new RuntimeException('rare');
    $b = new LogicException('common');
    capture($a);
    capture($b);
    capture($b);
    capture($b);

    $response = $this->getJson('watchtower/api/exceptions?sort=frequency');

    $response->assertOk()->assertJsonStructure([
        'data' => [['id', 'class', 'message', 'count', 'context_type']],
        'meta' => ['page', 'total', 'last_page'],
        'summary' => ['unresolved', 'resolved'],
    ]);
    expect($response->json('data.0.class'))->toBe(LogicException::class);
});

it('returns the overview summary', function () {
    capture(new RuntimeException('x'));

    $this->getJson('watchtower/api/overview')
        ->assertOk()
        ->assertJsonStructure([
            'jobs' => ['processed_24h', 'failed_24h', 'failed_total'],
            'schedule' => ['total', 'missed', 'failing'],
            'exceptions' => ['unresolved'],
            'meta' => ['recording', 'sampling_rate'],
        ])
        ->assertJsonPath('exceptions.unresolved', 1);
});
