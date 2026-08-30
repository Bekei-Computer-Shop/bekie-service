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
            'in_stock' => $this->resource['in_stock'],
            'low_stock' => $this->resource['low_stock'],
            'out_of_stock' => $this->resource['out_of_stock'],
            'total_stock_value' => $this->resource['total_stock_value'],
            'currency' => 'USD',
        ];
    }
}
