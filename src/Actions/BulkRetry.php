<?php

namespace Watchtower\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Watchtower\Support\FailedJobs;

/**
 * Bulk-retry failed jobs by various selectors. Every path resolves to a set of
 * native failed-job ids and calls queue:retry once, reusing Laravel's own retry
 * mechanism rather than re-dispatching by hand.
 */
class BulkRetry
{
    public function __construct(protected FailedJobs $failedJobs)
    {
    }

    /**
     * Retry every failed job.
     */
    public function all(): int
    {
        return $this->retryIds($this->failedJobs->all()->pluck('id')->all());
    }

    /**
     * Retry every failed job whose exception class matches.
     */
    public function byExceptionType(string $exceptionClass): int
    {
        $ids = $this->failedJobs->all()
            ->filter(fn ($job) => $job['exception_class'] === ltrim($exceptionClass, '\\'))
            ->pluck('id')
            ->all();

        return $this->retryIds($ids);
    }

    /**
     * Retry every failed job that failed within [from, to].
     */
    public function byTimeWindow(?string $from, ?string $to): int
    {
        $fromTs = $from ? Carbon::parse($from) : null;
        $toTs = $to ? Carbon::parse($to) : null;

        $ids = $this->failedJobs->all()
            ->filter(function ($job) use ($fromTs, $toTs) {
                if (! $job['failed_at']) {
                    return false;
                }
                $failedAt = Carbon::parse($job['failed_at']);

                if ($fromTs && $failedAt->lt($fromTs)) {
                    return false;
                }
                if ($toTs && $failedAt->gt($toTs)) {
                    return false;
                }

                return true;
            })
            ->pluck('id')
            ->all();

        return $this->retryIds($ids);
    }

    /**
     * Retry an explicit set of queues.
     */
    public function byQueue(string $queue): int
    {
        $ids = $this->failedJobs->all()
            ->filter(fn ($job) => $job['queue'] === $queue)
            ->pluck('id')
            ->all();

        return $this->retryIds($ids);
    }

    /**
     * @param  array<int, string>  $ids
     * @return int  number of jobs queued for retry
     */
    protected function retryIds(array $ids): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));

        if (empty($ids)) {
            return 0;
        }

        Artisan::call('queue:retry', ['id' => $ids]);

        return count($ids);
    }
}
