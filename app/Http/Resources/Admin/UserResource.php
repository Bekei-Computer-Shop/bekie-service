<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Shown next to the picked customer when creating an order.
            'phone' => $this->phone,
            // Only serialised where the relation was eager-loaded, so adding this
            // cannot turn other user listings into an N+1.
            'address' => $this->whenLoaded('defaultAddress', fn () => $this->addressPayload()),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')) ?: $this->getRoleNames(),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')) ?: $this->getAllPermissions()->pluck('name'),
        ];
    }

    /**
     * The customer's default address, keyed to match Order::$address_snapshot so
     * both can be rendered by the same client-side formatter. Null when the user
     * has no address on file.
     *
     * @return array<string, mixed>|null
     */
    private function addressPayload(): ?array
    {
        $address = $this->defaultAddress;

        if (! $address) {
            return null;
        }

        return [
            'label' => $address->label,
            'full_name' => $address->full_name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
        ];
    }
}
