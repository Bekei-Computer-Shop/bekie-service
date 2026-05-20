<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->in_stock,
            'track_inventory' => $this->track_inventory,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'image' => $this->image,
            'attributes' => $this->attributes,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
