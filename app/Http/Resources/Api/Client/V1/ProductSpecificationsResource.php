<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSpecificationsResource extends JsonResource
{
    public function toArray($request): array
    {
        $groups = [];
        $physicalAttributes = collect([
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
        ])
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value, string $key): array => ['key' => $key, 'value' => $value])
            ->values()
            ->all();

        if ($physicalAttributes !== []) {
            $groups[] = [
                'name' => 'Physical properties',
                'attributes' => $physicalAttributes,
            ];
        }

        $variants = $this->resource->relationLoaded('variants')
            ? $this->resource->variants
            : collect();

        foreach ($variants as $variant) {
            $attributes = collect($variant->attributes ?? [])
                ->map(fn ($value, string|int $key): array => ['key' => (string) $key, 'value' => $value])
                ->values()
                ->all();

            if ($attributes === []) {
                continue;
            }

            $groups[] = [
                'name' => $variant->name,
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'attributes' => $attributes,
            ];
        }

        return [
            'product_id' => $this->id,
            'groups' => $groups,
        ];
    }
}
