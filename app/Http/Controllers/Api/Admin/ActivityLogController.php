<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $logs = ActivityLog::query()
            ->with('actor')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('target_type'), fn ($query) => $query->where('target_type', $request->input('target_type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->latest('created_at')
            ->paginate(50);

        return $this->success([
            'items' => ActivityLogResource::collection($logs->items()),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'count' => $logs->count(),
            ],
        ]);
    }
}
