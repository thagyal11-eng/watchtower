<?php

namespace Watchtower\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Watchtower\Actions\ResolveException;
use Watchtower\Models\ExceptionRecord;

class ExceptionController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) config('watchtower.dashboard.per_page', 25);
        $sort = $request->query('sort', 'recent'); // recent|frequency
        $status = $request->query('status', 'unresolved'); // unresolved|resolved|all
        $context = $request->query('context'); // request|job|schedule|other

        $query = ExceptionRecord::query();

        if ($status === 'unresolved') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($context) {
            $query->where('context_type', $context);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('class', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc($sort === 'frequency' ? 'count' : 'last_seen_at');

        $paginator = $query->paginate($perPage, [
            'id', 'fingerprint', 'class', 'message', 'file', 'line',
            'context_type', 'count', 'first_seen_at', 'last_seen_at', 'resolved_at',
        ])->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => [
                'unresolved' => ExceptionRecord::query()->whereNull('resolved_at')->count(),
                'resolved' => ExceptionRecord::query()->whereNotNull('resolved_at')->count(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $record = ExceptionRecord::query()->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    public function resolve(int $id, ResolveException $action): JsonResponse
    {
        $record = ExceptionRecord::query()->findOrFail($id);
        $action->resolve($record);

        return response()->json(['message' => 'Exception resolved.', 'data' => $record]);
    }

    public function reopen(int $id, ResolveException $action): JsonResponse
    {
        $record = ExceptionRecord::query()->findOrFail($id);
        $action->reopen($record);

        return response()->json(['message' => 'Exception reopened.', 'data' => $record]);
    }
}
