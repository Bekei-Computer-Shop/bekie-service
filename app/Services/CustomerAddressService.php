<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    public const MAX_ADDRESSES = 20;

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $addresses = $this->activeShippingAddresses($lockedUser);

            if ($addresses->count() >= self::MAX_ADDRESSES) {
                throw ValidationException::withMessages([
                    'addresses' => ['You may save no more than '.self::MAX_ADDRESSES.' shipping addresses.'],
                ]);
            }

            $isDefault = $addresses->isEmpty();

            if ($isDefault) {
                $this->clearDefault($lockedUser);
            }

            return $lockedUser->addresses()->create($data + [
                'type' => 'shipping',
                'is_active' => true,
                'is_default' => $isDefault,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, int $addressId, array $data): Address
    {
        return DB::transaction(function () use ($user, $addressId, $data): Address {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $address = $this->findActiveShippingAddress($lockedUser, $addressId);
            $address->update($data);

            return $address->refresh();
        });
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $address = $this->findActiveShippingAddress($lockedUser, $addressId);
            $wasDefault = $address->is_default;
            $address->delete();

            if (! $wasDefault) {
                return;
            }

            $replacement = $lockedUser->addresses()
                ->where('type', 'shipping')
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                $this->clearDefault($lockedUser);
                $replacement->update(['is_default' => true]);
            }
        });
    }

    public function setDefault(User $user, int $addressId): Address
    {
        return DB::transaction(function () use ($user, $addressId): Address {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $address = $this->findActiveShippingAddress($lockedUser, $addressId);

            $this->clearDefault($lockedUser);
            $address->update(['is_default' => true]);

            return $address->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{address_id: int|null, snapshot: array<string, mixed>, country: string}
     */
    public function resolveCheckoutAddress(User $user, array $data): array
    {
        if (filled($data['address_id'] ?? null)) {
            $address = $this->findActiveShippingAddress($user, (int) $data['address_id']);

            return [
                'address_id' => $address->id,
                'snapshot' => $this->snapshot($address->getAttributes()),
                'country' => $address->country,
            ];
        }

        $inlineAddress = [
            'full_name' => $data['recipient_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'],
            'country' => strtoupper($data['country']),
            'delivery_note' => $data['delivery_note'] ?? null,
        ];

        $savedAddress = ($data['save_address'] ?? false)
            ? $this->create($user, $inlineAddress)
            : null;

        return [
            'address_id' => $savedAddress?->id,
            'snapshot' => $this->snapshot($inlineAddress),
            'country' => $inlineAddress['country'],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(array $address): array
    {
        return array_intersect_key($address, array_flip([
            'full_name',
            'phone',
            'email',
            'company',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'delivery_note',
        ]));
    }

    private function findActiveShippingAddress(User $user, int $addressId): Address
    {
        return $user->addresses()
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->whereKey($addressId)
            ->firstOrFail();
    }

    private function activeShippingAddresses(User $user)
    {
        return $user->addresses()
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->get();
    }

    private function clearDefault(User $user): void
    {
        $user->addresses()
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
