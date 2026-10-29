<?php

namespace App\Http\Resources\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\Concerns\SummarizesPromotion;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    use SummarizesPromotion;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'in_stock' => $this->in_stock,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnail' => $this->thumbnail,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_digital' => $this->is_digital,
            'views_count' => $this->views_count,
            'sales_count' => $this->sales_count,
            'sort_order' => $this->sort_order,
            'average_rating' => $this->average_rating ?? 0,
            'reviews_count' => $this->reviews_count ?? 0,
            'promotion' => $this->promotionSummary(),
        ];
    }
}
