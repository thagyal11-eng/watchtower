<?php

namespace Watchtower\Listeners;

use Illuminate\Support\Carbon;
use Throwable;
use Watchtower\Models\ExceptionRecord;
use Watchtower\Storage\MetricRepository;

/**
 * A lightweight built-in error tracker. Invoked from a reportable callback on
 * the host's exception handler — it records and returns, never swallowing the
 * exception or altering app behaviour. Occurrences group by a fingerprint of
 * class + file + line.
 */
class ExceptionListener
{
    public function __construct(protected MetricRepository $repository)
    {
    }

    public function handle(Throwable $e): void
    {
        if (! $this->repository->recording('exceptions')) {
            return;
        }

        $class = get_class($e);

        if ($this->repository->ignored('exceptions', $class)) {
            return;
        }

        $fingerprint = $this->fingerprint($e);
        $now = Carbon::now();
        $contextType = $this->contextType();

        $message = $this->repository->truncate($e->getMessage(), 'message');
        $trace = $this->repository->truncate($e->getTraceAsString(), 'trace');

        // Exceptions are always recorded — bypass sampling.
        $this->repository->write(function () use ($e, $class, $fingerprint, $now, $contextType, $message, $trace) {
            $record = ExceptionRecord::query()->firstOrNew(['fingerprint' => $fingerprint]);

            if (! $record->exists) {
                $record->fill([
                    'class' => $class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'count' => 0,
                    'first_seen_at' => $now,
                ]);
            }

            $record->fill([
                'message' => $message,
                'trace' => $trace,
                'context_type' => $contextType,
                'last_seen_at' => $now,
            ]);
            $record->count = $record->count + 1;

            // A fresh occurrence re-opens a previously resolved error.
            $record->resolved_at = null;

            $record->save();
        }, force: true);
    }

    protected function fingerprint(Throwable $e): string
    {
        return hash('sha256', get_class($e).'|'.$e->getFile().'|'.$e->getLine());
    }

    /**
     * Best-effort detection of where the exception was thrown so the dashboard
     * can cross-link request / job / schedule contexts.
     */
    protected function contextType(): string
    {
        if (app()->runningInConsole()) {
            // Distinguish a queue worker from a scheduled command run.
            $command = $_SERVER['argv'][1] ?? '';

            if (str_contains($command, 'queue:')) {
                return 'job';
            }

            if (str_contains($command, 'schedule:')) {
                return 'schedule';
            }

            return 'other';
        }

        return 'request';
    }
}
