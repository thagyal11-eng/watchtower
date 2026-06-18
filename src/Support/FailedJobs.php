<?php

namespace Watchtower\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Thin adapter over Laravel's native failed-job store (queue.failer). Reading,
 * retrying and deleting all go through the framework so behaviour is identical
 * across the database, redis and sqs drivers — we never re-dispatch by hand.
 */
class FailedJobs
{
    public function provider()
    {
        return app('queue.failer');
    }

    /**
     * Normalise every native failed job into a flat array for the API.
     *
     * @return Collection<int, array>
     */
    public function all(): Collection
    {
        $provider = $this->provider();

        if (! $provider) {
            return collect();
        }

        try {
            $jobs = $provider->all();
        } catch (\Throwable) {
            // Failed-job store unavailable/misconfigured — degrade gracefully
            // rather than crashing the dashboard.
            return collect();
        }

        return collect($jobs)->map(function ($job) {
            $payload = json_decode($job->payload ?? '{}', true) ?: [];

            return [
                'id' => (string) $job->id,
                'uuid' => $payload['uuid'] ?? (string) $job->id,
                'connection' => $job->connection ?? null,
                'queue' => $job->queue ?? null,
                'name' => $this->displayName($payload),
                'exception_class' => $this->exceptionClass($job->exception ?? ''),
                'exception_summary' => $this->firstLine($job->exception ?? ''),
                'exception' => $this->cap($job->exception ?? ''),
                'failed_at' => $this->parseDate($job->failed_at ?? null),
            ];
        });
    }

    public function distinctExceptionClasses(): array
    {
        return $this->all()
            ->pluck('exception_class')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function distinctQueues(): array
    {
        return $this->all()
            ->pluck('queue')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function displayName(array $payload): string
    {
        return $payload['displayName']
            ?? ($payload['data']['commandName'] ?? 'Unknown job');
    }

    protected function exceptionClass(string $exception): ?string
    {
        if (preg_match('/^([\\\\\w]+):/', $exception, $m)) {
            return ltrim($m[1], '\\');
        }

        $first = strtok($exception, "\n");

        return $first ? Str::before($first, ' ') : null;
    }

    protected function firstLine(string $exception): string
    {
        return Str::limit((string) strtok($exception, "\n"), 240);
    }

    protected function parseDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function cap(string $exception): string
    {
        $cap = (int) config('watchtower.limits.trace', 16384);

        return strlen($exception) > $cap ? substr($exception, 0, $cap)."\n… [truncated]" : $exception;
    }
}
