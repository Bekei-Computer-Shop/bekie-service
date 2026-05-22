<?php

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'data' => $this->resource,
        ];
    }
}
