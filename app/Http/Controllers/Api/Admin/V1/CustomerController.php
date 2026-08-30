<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\StoreCustomerRequest;
use App\Http\Requests\Api\Admin\V1\UpdateCustomerRequest;
use App\Http\Resources\Api\Admin\V1\CustomerResource;
use App\Models\CustomerGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Shopper accounts, as managed from the admin Customers screen.
 *
 * A "customer" here is any non-admin user — either holding the `customer` role
 * where that role exists, or simply `is_admin = false` where it does not. Admin
 * accounts are managed by AdministratorController and are invisible to every
 * action below, including by direct id.
 */
class CustomerController extends BaseAdminController
{
    private const PER_PAGE = 15;

    /** The list screen has no pager, so it asks for one large page. */
    private const MAX_PER_PAGE = 200;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $customers = $this->customerQuery()
            ->with(['defaultAddress', 'customerGroups'])
            // whereLike(caseSensitive: false) so the match is ILIKE on
            // Postgres — a plain LIKE there would only ever hit the lowercase
            // columns, missing every capitalised name.
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $match) => $match
                    ->whereLike('first_name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('last_name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('phone', "%{$search}%", caseSensitive: false)
            ))
            // created_at alone is not a stable sort — the seeded rows share a
            // second — and an unstable sort lets a row fall between pages.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', self::PER_PAGE), self::MAX_PER_PAGE));

        return $this->success(CustomerResource::collection($customers));
    }

    public function show(User $user): JsonResponse
    {
        $this->assertCustomer($user);

        return $this->success(new CustomerResource($this->hydrate($user)));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = new User([
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'is_admin' => false,
        ]);

        // `name` is an accessor over first_name/last_name, so it goes through
        // the mutator rather than being passed alongside the real columns.
        $customer->name = $data['name'];

        // No password field on the admin form: give the account an unguessable
        // one so the row is valid, and let the customer set their own by reset.
        $customer->password = Hash::make($data['password'] ?? Str::random(32));
        $customer->save();

        if ($role = $this->customerRole()) {
            $customer->assignRole($role);
        }

        $this->applyStatus($customer, $data['status']);
        $this->syncAddress($customer, $data['address'] ?? '');

        return $this->created(new CustomerResource($this->hydrate($customer)));
    }

    public function update(UpdateCustomerRequest $request, User $user): JsonResponse
    {
        $this->assertCustomer($user);

        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->avatar = $data['avatar'] ?? null;

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $this->applyStatus($user, $data['status']);
        $this->syncAddress($user, $data['address'] ?? '');

        return $this->success(new CustomerResource($this->hydrate($user)));
    }

    /**
     * Soft-deletes the account. Orders keep their `customer_snapshot`, so the
     * order history stays readable after the customer is gone.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->assertCustomer($user);

        $user->delete();

        return $this->noContent();
    }

    /**
     * Base query for shopper accounts, carrying the two order aggregates the
     * list shows. Only `completed` orders count towards either: a pending or
     * cancelled order is not money the shop has taken.
     */
    private function customerQuery(): Builder
    {
        $customerRole = $this->customerRole();

        return User::query()
            ->when($customerRole, fn (Builder $query) => $query->whereHas(
                'roles',
                fn ($roleQuery) => $roleQuery->where('roles.id', $customerRole->id)
            ))
            ->when(! $customerRole, fn (Builder $query) => $query->where('is_admin', false))
            ->withCount(['orders as completed_orders_count' => fn ($query) => $query->where('status', 'completed')])
            ->withSum(['orders as completed_orders_sum_grand_total' => fn ($query) => $query->where('status', 'completed')], 'grand_total');
    }

    private function customerRole(): ?Role
    {
        return Role::query()
            ->where('name', 'customer')
            ->where('guard_name', 'api')
            ->first();
    }

    /**
     * Route model binding resolves any user by id, so without this an admin id
     * would be readable and editable through the customer endpoints.
     */
    private function assertCustomer(User $user): void
    {
        abort_if($user->is_admin, 404);
    }

    /**
     * Load the relations and aggregates CustomerResource reads.
     *
     * `withCount`/`withSum` only exist on a query builder, so a single model
     * picks the same two aggregates up afterwards.
     */
    private function hydrate(User $user): User
    {
        $user->load(['defaultAddress', 'customerGroups']);
        $user->loadCount(['orders as completed_orders_count' => fn ($query) => $query->where('status', 'completed')]);
        $user->loadSum(['orders as completed_orders_sum_grand_total' => fn ($query) => $query->where('status', 'completed')], 'grand_total');

        return $user;
    }

    /**
     * Write the active/vip/inactive badge back to the columns behind it.
     *
     * `inactive` clears `is_active` but never sets `is_banned`: banning is a
     * separate, heavier action and this screen must not perform it silently.
     * A ban set elsewhere still reads back as `inactive`.
     */
    private function applyStatus(User $user, string $status): void
    {
        $user->is_active = $status !== 'inactive';
        $user->save();

        $vipGroup = CustomerGroup::firstOrCreate(
            ['slug' => CustomerResource::VIP_GROUP_SLUG],
            ['name' => 'VIP', 'description' => 'High-value customers.', 'is_active' => true],
        );

        if ($status === 'vip') {
            $user->customerGroups()->syncWithoutDetaching([$vipGroup->id]);
        } else {
            $user->customerGroups()->detach($vipGroup->id);
        }
    }

    /**
     * The admin form edits the address as one free-text field, so what is typed
     * goes into `address_line_1` and the structured parts are cleared — that is
     * what makes CustomerResource::formatAddress() read back exactly what was
     * entered. `city` and `country` are NOT NULL, so they are emptied rather
     * than nulled; formatAddress() drops empty parts either way.
     *
     * A structured address set from the storefront therefore survives until an
     * admin edits this field, at which point it flattens to the typed line.
     */
    private function syncAddress(User $user, string $address): void
    {
        $address = trim($address);
        $existing = $user->defaultAddress()->first();

        // Nothing typed: leave the customer address-less rather than creating an
        // empty row. An address already on file is left alone.
        if ($address === '') {
            return;
        }

        if ($existing && CustomerResource::formatAddress($existing) === $address) {
            return;
        }

        $columns = [
            'address_line_1' => $address,
            'address_line_2' => null,
            'city' => '',
            'state' => null,
            'postal_code' => null,
            'country' => '',
        ];

        if ($existing) {
            $existing->update($columns);
        } else {
            $user->addresses()->create($columns + [
                'type' => 'shipping',
                'label' => 'Home',
                'full_name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        $user->unsetRelation('defaultAddress');
    }
}
