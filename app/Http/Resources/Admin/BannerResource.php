<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_desktop' => $this->image_desktop,
            'image_mobile' => $this->image_mobile,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'position' => $this->position,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
