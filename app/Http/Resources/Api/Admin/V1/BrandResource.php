<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'logo' => $brand->logo,
            'website' => $brand->website,
            'description' => $brand->description,
            'meta_title' => $brand->meta_title,
            'meta_description' => $brand->meta_description,
            'facebook' => $brand->facebook,
            'instagram' => $brand->instagram,
            'twitter' => $brand->twitter,
            'youtube' => $brand->youtube,
            'is_active' => (bool) $brand->is_active,
            'is_featured' => (bool) $brand->is_featured,
            'sort_order' => (int) $brand->sort_order,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $brand->created_at?->toIso8601String(),
            'updated_at' => $brand->updated_at?->toIso8601String(),
            'deleted_at' => $brand->deleted_at?->toIso8601String(),
        ];
    }
}
