<?php

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'reference' => $this->reference,
            'note' => $this->note,
            'actor' => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
                'email' => $this->actor?->email,
            ],
            'order' => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
