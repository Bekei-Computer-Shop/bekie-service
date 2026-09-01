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
            // The promo price this line actually charged, when the product was
            // on sale at checkout (null otherwise).
            'sale_price' => $this->sale_price !== null
                ? number_format((float) $this->sale_price, 2, '.', '')
                : null,
            // Per-line discount frozen at purchase time.
            'discount' => number_format((float) $this->discount, 2, '.', ''),
            'line_total' => number_format((float) $this->total, 2, '.', ''),
            'product' => [
                'id' => $this->product?->uuid ?? $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ],
        ];
    }
}
