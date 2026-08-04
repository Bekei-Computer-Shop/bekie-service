<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\TeamActivityLogResource;
use App\Http\Resources\Admin\VisitorLogResource;
use App\Models\TeamActivityLog;
use App\Models\VisitorLog;
use Illuminate\Http\Request;

class LogController extends BaseAdminController
{
    public function visitors(Request $request)
    {
        $logs = VisitorLog::when($request->filled('ip_address'), fn ($query) => $query->where('ip_address', $request->input('ip_address')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->latest('created_at')
            ->paginate(50);

        return $this->success(VisitorLogResource::collection($logs));
    }

    public function team(Request $request)
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
