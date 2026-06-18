<?php

namespace Watchtower\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Watchtower\Actions\BulkRetry;
use Watchtower\Actions\DeleteFailedJob;
use Watchtower\Actions\RetryJob;
use Watchtower\Models\JobRecord;
use Watchtower\Support\FailedJobs;

class QueueController
{
    /** window key => minutes */
    protected const WINDOWS = [
        'hour' => 60,
        'day' => 1440,
        'week' => 10080,
    ];

    public function metrics(Request $request): JsonResponse
    {
        $window = $request->query('window', 'day');
        $minutes = self::WINDOWS[$window] ?? self::WINDOWS['day'];
        $since = Carbon::now()->subMinutes($minutes);

        // Pull only the columns we need for the window. Retention keeps this
        // bounded; percentiles/throughput are computed in PHP for driver
        // portability (no DB-specific percentile/date functions).
        $rows = JobRecord::query()
            ->where(function ($q) use ($since) {
                $q->where('finished_at', '>=', $since)
                    ->orWhereIn('status', ['queued', 'processing']);
            })
            ->get(['status', 'queue', 'finished_at', 'duration_ms']);

        $processed = $rows->where('status', 'processed')->whereNotNull('finished_at')
            ->filter(fn ($r) => $r->finished_at >= $since);
        $failed = $rows->where('status', 'failed')->whereNotNull('finished_at')
            ->filter(fn ($r) => $r->finished_at >= $since);

        $durations = $processed->pluck('duration_ms')->filter(fn ($d) => $d !== null)->sort()->values();

        return response()->json([
            'window' => $window,
            'totals' => [
                'pending' => $rows->where('status', 'queued')->count(),
                'processing' => $rows->where('status', 'processing')->count(),
                'processed' => $processed->count(),
                'failed' => $failed->count(),
            ],
            'duration' => [
                'avg_ms' => $durations->isNotEmpty() ? (int) round($durations->avg()) : null,
                'p95_ms' => $this->percentile($durations->all(), 95),
            ],
            'per_queue' => $this->perQueue($rows, $since),
            'throughput' => $this->throughput($processed, $failed, $minutes),
            'pending_estimate' => $this->pendingEstimate(),
        ]);
    }

    public function failed(Request $request, FailedJobs $failedJobs): JsonResponse
    {
        $perPage = (int) config('watchtower.dashboard.per_page', 25);
        $page = max(1, (int) $request->query('page', 1));

        $jobs = $failedJobs->all();

        if ($queue = $request->query('queue')) {
            $jobs = $jobs->where('queue', $queue)->values();
        }
        if ($exception = $request->query('exception_class')) {
            $jobs = $jobs->where('exception_class', ltrim($exception, '\\'))->values();
        }
        if ($search = $request->query('search')) {
            $jobs = $jobs->filter(fn ($j) => str_contains(strtolower($j['name'].' '.$j['exception_summary']), strtolower($search)))->values();
        }

        $total = $jobs->count();
        $items = $jobs->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
            'filters' => [
                'exception_classes' => $failedJobs->distinctExceptionClasses(),
                'queues' => $failedJobs->distinctQueues(),
            ],
        ]);
    }

    public function retry(string $id, RetryJob $action): JsonResponse
    {
        $action->execute($id);

        return response()->json(['message' => 'Job queued for retry.']);
    }

    public function destroy(string $id, DeleteFailedJob $action): JsonResponse
    {
        $action->execute($id);

        return response()->json(['message' => 'Failed job deleted.']);
    }

    public function bulkRetry(Request $request, BulkRetry $action): JsonResponse
    {
        $mode = $request->input('mode', 'all');

        $count = match ($mode) {
            'exception' => $action->byExceptionType((string) $request->input('exception_class')),
            'window' => $action->byTimeWindow($request->input('from'), $request->input('to')),
            'queue' => $action->byQueue((string) $request->input('queue')),
            default => $action->all(),
        };

        return response()->json([
            'message' => "{$count} job(s) queued for retry.",
            'count' => $count,
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    protected function perQueue($rows, Carbon $since): array
    {
        return $rows->groupBy(fn ($r) => $r->queue ?: 'default')
            ->map(function ($group, $queue) use ($since) {
                $proc = $group->where('status', 'processed')->filter(fn ($r) => $r->finished_at && $r->finished_at >= $since);
                $fail = $group->where('status', 'failed')->filter(fn ($r) => $r->finished_at && $r->finished_at >= $since);

                return [
                    'queue' => $queue,
                    'pending' => $group->where('status', 'queued')->count(),
                    'processing' => $group->where('status', 'processing')->count(),
                    'processed' => $proc->count(),
                    'failed' => $fail->count(),
                ];
            })
            ->values()
            ->all();
    }

    protected function throughput($processed, $failed, int $minutes): array
    {
        $buckets = 24;
        $bucketMinutes = max(1, (int) round($minutes / $buckets));
        $now = Carbon::now();
        $series = [];

        for ($i = $buckets - 1; $i >= 0; $i--) {
            $end = $now->copy()->subMinutes($i * $bucketMinutes);
            $start = $end->copy()->subMinutes($bucketMinutes);

            $series[] = [
                'at' => $end->toIso8601String(),
                'processed' => $processed->filter(fn ($r) => $r->finished_at && $r->finished_at > $start && $r->finished_at <= $end)->count(),
                'failed' => $failed->filter(fn ($r) => $r->finished_at && $r->finished_at > $start && $r->finished_at <= $end)->count(),
            ];
        }

        return $series;
    }

    protected function percentile(array $sorted, int $p): ?int
    {
        if (empty($sorted)) {
            return null;
        }

        $index = (int) ceil(($p / 100) * count($sorted)) - 1;
        $index = max(0, min($index, count($sorted) - 1));

        return (int) round($sorted[$index]);
    }

    /**
     * Best-effort pending estimate for the database driver (where we can read
     * the jobs table directly). Other drivers degrade gracefully to null with a
     * label so the UI never shows a wrong number or crashes.
     */
    protected function pendingEstimate(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver === 'database') {
            try {
                $table = config("queue.connections.{$connection}.table", 'jobs');
                $count = app('db')->table($table)->count();

                return ['supported' => true, 'driver' => $driver, 'count' => $count];
            } catch (\Throwable) {
                return ['supported' => false, 'driver' => $driver, 'count' => null];
            }
        }

        return ['supported' => false, 'driver' => $driver, 'count' => null];
    }
}
