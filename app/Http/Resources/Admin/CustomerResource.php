<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'order_count' => $this->whenLoaded('orders', fn () => $this->orders->count()),
            'total_spent' => $this->whenLoaded('orders', fn () => $this->orders->sum('total')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
