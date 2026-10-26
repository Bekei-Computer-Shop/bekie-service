<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $productsCount = (int) ($this->products_count ?? 0);
        if ($this->relationLoaded('children')) {
            $productsCount += $this->children->sum('products_count');
        }

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'products_count' => $productsCount,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
