<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\Concerns\SummarizesPromotion;
use Illuminate\Http\Resources\Json\JsonResource;

class RelatedProductResource extends JsonResource
{
    use SummarizesPromotion;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'promotion' => $this->promotionSummary(),
            'thumbnail' => $this->thumbnail,
            'is_featured' => (bool) $this->is_featured,
            'in_stock' => (bool) $this->in_stock,
            // Approved reviews only; present where the controller aggregated them.
            'average_rating' => $this->when($this->resource->hasAttribute('average_rating'), fn (): float => round((float) $this->average_rating, 1)),
            'reviews_count' => $this->when($this->resource->hasAttribute('reviews_count'), fn (): int => (int) $this->reviews_count),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null),
        ];
    }
}
