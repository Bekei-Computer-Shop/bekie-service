<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Serializes a stockable row for the inventory list and detail views.
 *
 * Used instead of dumping the raw Eloquent model, which leaked internal
 * columns (description, meta strings, warehouse internals) into every list
 * response and dragged the full row in even when the page only needed a few
 * columns.
 */
class StockItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->when($this->resource instanceof Product, $this->uuid),
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'thumbnail' => $this->thumbnail,
            'warehouse_location' => $this->warehouse_location ?? 'Main Warehouse',
            'stockable_type' => $this->resource instanceof Product ? 'product' : 'variant',
            'stock_quantity' => (int) $this->stock_quantity,
            'reserved_stock' => (int) $this->reserved_stock,
            'damaged_stock' => (int) $this->damaged_stock,
            'incoming_stock' => (int) $this->incoming_stock,
            'available_stock' => $this->available_stock,
            'min_stock_alert' => (int) $this->min_stock_alert,
            'max_stock_level' => $this->max_stock_level !== null ? (int) $this->max_stock_level : null,
            'reorder_point' => (int) ($this->reorder_point ?? 0),
            'track_inventory' => (bool) $this->track_inventory,
            'in_stock' => (bool) $this->in_stock,
            'stock_status' => $this->stock_status,
            'stock_value' => $this->cost_price !== null
                ? round((float) $this->cost_price * max(0, (int) $this->stock_quantity), 2)
                : null,
            'price' => $this->price !== null ? (float) $this->price : null,
            'last_movement_at' => $this->last_movement_at
                ? Carbon::parse($this->last_movement_at)->toIso8601String()
                : null,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug]
                : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand
                ? ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug]
                : null),
            'variants' => $this->when($this->resource instanceof Product, fn () => $this->whenLoaded('variants', function () {
                return collect($this->variants)->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'stock_quantity' => (int) $variant->stock_quantity,
                    'reserved_stock' => (int) $variant->reserved_stock,
                    'available_stock' => $variant->available_stock,
                    'incoming_stock' => (int) $variant->incoming_stock,
                    'damaged_stock' => (int) $variant->damaged_stock,
                    'min_stock_alert' => (int) $variant->min_stock_alert,
                    'max_stock_level' => $variant->max_stock_level !== null ? (int) $variant->max_stock_level : null,
                    'track_inventory' => (bool) $variant->track_inventory,
                    'in_stock' => (bool) $variant->in_stock,
                    'stock_status' => $variant->stock_status,
                    'is_active' => (bool) $variant->is_active,
                    'image' => $variant->image,
                ])->all();
            })),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
