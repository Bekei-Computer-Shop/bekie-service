<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_type' => $this->movement_type,
            'quantity' => (int) $this->quantity,
            'previous_quantity' => (int) $this->previous_quantity,
            'new_quantity' => (int) $this->new_quantity,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'source_location' => $this->source_location,
            'destination_location' => $this->destination_location,
            'metadata' => $this->metadata ?? [],
            'created_by' => $this->createdBy ? [
                'id'    => $this->createdBy->id,
                'name'  => trim(($this->createdBy->first_name ?? '').' '.($this->createdBy->last_name ?? '')),
                'email' => $this->createdBy->email,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'stockable_type' => $this->stockable_type,
            'stockable_id' => $this->stockable_id,
            'note' => $this->metadata['note'] ?? null,
            'stockable_name' => $this->whenLoaded('stockable', fn() => $this->stockable?->name),
            'stockable_sku'  => $this->whenLoaded('stockable', fn() => $this->stockable?->sku),
        ];
    }
}
