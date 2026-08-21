<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'value' => number_format((float) $this->value, 2, '.', ''),
            'min_order_amount' => $this->min_order_amount === null ? null : number_format((float) $this->min_order_amount, 2, '.', ''),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'user_limit' => $this->user_limit,
            'used_count' => $this->used_count,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            'banner_image' => $this->banner_image,
        ];
    }
}
