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
            'adjustment_type' => $this->metadata['adjustment_type'] ?? null,
            'direction' => $this->metadata['direction'] ?? null,
            'quantity' => (int) $this->quantity,
            'previous_quantity' => (int) $this->previous_quantity,
            'new_quantity' => (int) $this->new_quantity,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'source_location' => $this->source_location,
            'destination_location' => $this->destination_location,
            'metadata' => $this->metadata ?? [],
            'note' => $this->metadata['notes'] ?? $this->metadata['note'] ?? null,
            'attachment' => $this->metadata['attachment'] ?? null,
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => trim(($this->createdBy->first_name ?? '').' '.($this->createdBy->last_name ?? '')),
                'email' => $this->createdBy->email,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'stockable_type' => $this->stockable_type,
            'stockable_id' => $this->stockable_id,
            'stockable_name' => $this->whenLoaded('stockable', fn () => $this->stockable?->name),
            'stockable_sku' => $this->whenLoaded('stockable', fn () => $this->stockable?->sku),
        ];
    }
}
