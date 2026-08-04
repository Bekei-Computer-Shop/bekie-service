<?php

namespace App\Http\Resources\Admin\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'base_price' => $this->base_price,
            'brand' => $this->whenLoaded('brand'),
            'categories' => $this->whenLoaded('categories'),
            'tags' => $this->whenLoaded('tags'),
            'variants' => $this->whenLoaded('variants', function() {
                return $this->variants->map(fn($v) => array_merge($v->toArray(), [
                    'stock' => $v->inventory?->quantity ?? 0
                ]));
            }),
            'images' => $this->whenLoaded('images'),
            'created_at' => $this->created_at,
        ];
    }
}
