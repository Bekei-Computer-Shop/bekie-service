<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->resource;

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'code' => $supplier->code,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'tax_id' => $supplier->tax_id,
            'notes' => $supplier->notes,
            'is_active' => (bool) $supplier->is_active,
            'is_default' => (bool) $supplier->is_default,
            'products_count' => $this->whenCounted('products'),
            'stock_movements_count' => $this->whenCounted('stockMovements'),
            'created_at' => $supplier->created_at?->toIso8601String(),
            'updated_at' => $supplier->updated_at?->toIso8601String(),
            'deleted_at' => $supplier->deleted_at?->toIso8601String(),
        ];
    }
}
