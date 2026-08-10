<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'qty' => $this->quantity,
            // Cast before formatting: Postgres returns decimal columns as
            // strings, which number_format() rejects under strict_types.
            'unit_price' => number_format((float) $this->unit_price, 2, '.', ''),
            'line_total' => number_format((float) $this->total, 2, '.', ''),
            'product' => [
                'id' => $this->product?->uuid ?? $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ],
        ];
    }
}
