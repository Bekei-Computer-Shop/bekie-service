<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'qty' => $this->quantity,
            'unit_price' => number_format($this->unit_price, 2, '.', ''),
            'line_total' => number_format($this->total, 2, '.', ''),
            'product' => [
                'id' => $this->product?->uuid ?? $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ],
        ];
    }
}
