<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Resources\Api\Admin\V1\TeamActivityLogResource;
use App\Http\Resources\Api\Admin\V1\VisitorLogResource;
use App\Models\TeamActivityLog;
use App\Models\VisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends BaseAdminController
{
    public function visitors(Request $request): JsonResponse
    {
        $logs = VisitorLog::when($request->filled('ip_address'), fn ($query) => $query->where('ip_address', $request->input('ip_address')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->latest('created_at')
            ->paginate(50);

        return $this->success(VisitorLogResource::collection($logs));
    }

    public function team(Request $request): JsonResponse
    {
        $logs = TeamActivityLog::with('actor')
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->input('actor_id')))
            ->when($request->filled('event_type'), fn ($query) => $query->where('event_type', $request->input('event_type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->latest('created_at')
            ->paginate(50);

        return $this->success(TeamActivityLogResource::collection($logs));
    }
}
