<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shopper as the admin Customers screen needs them.
 *
 * Deliberately separate from the admin `UserResource`: that one is shaped for
 * the Administrators screen (roles, permissions, token counts) and is shared by
 * several endpoints. This one drops all of that and adds the three things the
 * customer list actually shows — a delivery address, a lifetime order count and
 * lifetime spend.
 *
 * `orders_count` / `total_spent` are only present when the controller asked for
 * them; without the aggregate they read as 0 rather than firing a query per row.
 */
class CustomerResource extends JsonResource
{
    /** Group slug that promotes a customer to the VIP badge. */
    public const VIP_GROUP_SLUG = 'vip';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'address' => self::formatAddress($user->defaultAddress),
            'status' => self::statusOf($user),
            'is_active' => (bool) $user->is_active,
            'is_banned' => (bool) $user->is_banned,
            // withCount/withSum leave these null when the row has no orders, so
            // both coalesce to zero.
            'orders_count' => (int) ($user->completed_orders_count ?? 0),
            'total_spent' => round((float) ($user->completed_orders_sum_grand_total ?? 0), 2),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * active | vip | inactive — the three badges the admin list renders.
     *
     * Deactivated and banned both read as `inactive`: the screen has one "not
     * currently shopping" state, and a ban is the stronger form of it.
     * VIP comes from membership of the `vip` customer group, so it is data an
     * admin can actually change rather than a threshold hardcoded here.
     */
    public static function statusOf(User $user): string
    {
        if ($user->is_banned || ! $user->is_active) {
            return 'inactive';
        }

        $groups = $user->relationLoaded('customerGroups')
            ? $user->customerGroups
            : $user->customerGroups()->get();

        return $groups->contains('slug', self::VIP_GROUP_SLUG) ? 'vip' : 'active';
    }

    /**
     * Flatten an address into the single line the admin screen shows and edits.
     *
     * Empty parts are dropped, so a customer whose address is only a street
     * line reads back exactly what was typed — see the note on
     * `CustomerController::syncAddress()` about how that round-trip is kept.
     */
    public static function formatAddress(?Address $address): string
    {
        if (! $address) {
            return '';
        }

        $parts = array_filter([
            $address->address_line_1,
            $address->address_line_2,
            $address->city,
            trim(($address->state ?? '').' '.($address->postal_code ?? '')),
            $address->country,
        ], fn ($part) => filled($part));

        return implode(', ', array_map('trim', $parts));
    }
}
