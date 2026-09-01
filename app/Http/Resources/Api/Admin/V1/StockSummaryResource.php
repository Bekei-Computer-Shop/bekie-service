<?php

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'],
            'total_skus' => $this->resource['total_skus'],
            'total_units' => $this->resource['total_units'],
            'total_variants' => $this->resource['total_variants'],
            'in_stock' => $this->resource['in_stock'],
            'low_stock' => $this->resource['low_stock'],
            'out_of_stock' => $this->resource['out_of_stock'],
            'overstock' => $this->resource['overstock'],
            'pending' => $this->resource['pending'],
            'reserved_units' => $this->resource['reserved_units'],
            'incoming_units' => $this->resource['incoming_units'],
            'damaged_units' => $this->resource['damaged_units'],
            'total_stock_value' => $this->resource['total_stock_value'],
            'currency' => 'USD',
        ];
    }
}
