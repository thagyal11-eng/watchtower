<?php

namespace Watchtower\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Watchtower\Models\ExceptionRecord;
use Watchtower\Models\JobRecord;
use Watchtower\Support\FailedJobs;
use Watchtower\Support\ScheduleInspector;

class OverviewController
{
    public function index(ScheduleInspector $inspector, FailedJobs $failedJobs): JsonResponse
    {
        $since = Carbon::now()->subDay();

        $tasks = $inspector->tasks();

        return response()->json([
            'jobs' => [
                'processed_24h' => JobRecord::query()
                    ->where('status', 'processed')
                    ->where('finished_at', '>=', $since)->count(),
                'failed_24h' => JobRecord::query()
                    ->where('status', 'failed')
                    ->where('finished_at', '>=', $since)->count(),
                'failed_total' => $failedJobs->all()->count(),
            ],
            'schedule' => [
                'total' => $tasks->count(),
                'missed' => $tasks->where('missed', true)->count(),
                'failing' => $tasks->where('last_status', 'failed')->count(),
            ],
            'exceptions' => [
                'unresolved' => ExceptionRecord::query()->whereNull('resolved_at')->count(),
            ],
            'meta' => [
                'recording' => config('watchtower.recording'),
                'sampling_rate' => (float) config('watchtower.sampling.rate', 1.0),
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
