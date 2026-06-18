<?php

namespace Watchtower\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Watchtower\Actions\RunScheduledTask;
use Watchtower\Models\ScheduleRun;
use Watchtower\Support\ScheduleInspector;

class ScheduleController
{
    public function index(ScheduleInspector $inspector): JsonResponse
    {
        $tasks = $inspector->tasks();

        return response()->json([
            'tasks' => $tasks,
            'summary' => [
                'total' => $tasks->count(),
                'missed' => $tasks->where('missed', true)->count(),
                'failing' => $tasks->where('last_status', 'failed')->count(),
            ],
        ]);
    }

    public function run(Request $request, ScheduleInspector $inspector, RunScheduledTask $action): JsonResponse
    {
        $key = (string) $request->input('key');

        $event = $inspector->find($key);

        if (! $event) {
            return response()->json(['message' => 'Scheduled task not found.'], 404);
        }

        $result = $action->execute($event);

        return response()->json([
            'message' => 'Task executed.',
            'result' => $result,
        ]);
    }

    /**
     * Recent run history for a single task key (used by the expandable row).
     */
    public function history(Request $request): JsonResponse
    {
        $key = (string) $request->input('key');

        $runs = ScheduleRun::query()
            ->where('task_key', $key)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return response()->json(['runs' => $runs]);
    }
}
