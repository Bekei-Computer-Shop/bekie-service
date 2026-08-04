<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'kpis' => $this->resource['kpis'] ?? [],
            'daily_reports' => $this->resource['daily_reports'] ?? [],
            'sales_by_category' => $this->resource['sales_by_category'] ?? [],
            'visitor_log' => VisitorLogResource::collection($this->resource['visitor_log'] ?? []),
            'team_activity' => TeamActivityLogResource::collection($this->resource['team_activity'] ?? []),
        ];
    }
}
