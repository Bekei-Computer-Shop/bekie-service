<?php

declare(strict_types=1);

use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

/** @return array{0: User, 1: string} */
function customerAdmin(array $attrs = []): array
{
    $user = User::factory()->superAdmin()->create($attrs);

    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return [$user, (new AdminAuthService)->createAdminToken($user)['access_token']];
}

function asAdmin(string $token)
{
    return test()->withHeaders(['Authorization' => 'Bearer '.$token]);
}

/** A completed order is the only kind that counts toward a customer's spend. */
function completedOrder(User $customer, float $total, string $status = 'completed'): Order
{
    return Order::create([
        'order_number' => 'T-'.str_pad((string) Order::query()->count(), 5, '0', STR_PAD_LEFT),
        'user_id' => $customer->id,
        'status' => $status,
        'currency' => 'USD',
        'payment_method' => 'cod',
        'payment_status' => 'paid',
        'subtotal' => $total,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'grand_total' => $total,
    ]);
}

test('index lists shoppers with their completed-order totals and hides admins', function (): void {
    [$admin, $token] = customerAdmin();

    $shopper = User::factory()->create(['first_name' => 'Sokha', 'last_name' => 'Chan', 'is_admin' => false]);
    completedOrder($shopper, 100.50);
    completedOrder($shopper, 49.50);
    // Not completed, so it must not reach either aggregate.
    completedOrder($shopper, 999.00, 'pending');

    $response = asAdmin($token)->getJson('/api/v1/admin/customers');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($shopper->id)
        ->and($ids)->not->toContain($admin->id);

    $row = collect($response->json('data'))->firstWhere('id', $shopper->id);
    // JSON has one number type, so a whole-dollar total decodes as int 150 —
    // toEqual rather than toBe.
    expect($row['orders_count'])->toBe(2)
        ->and($row['total_spent'])->toEqual(150.0)
        ->and($row['status'])->toBe('active');
});

test('search matches a name regardless of case', function (): void {
    [, $token] = customerAdmin();

    $match = User::factory()->create(['first_name' => 'Bopha', 'last_name' => 'Sok', 'is_admin' => false]);
    User::factory()->create(['first_name' => 'Dara', 'last_name' => 'Lim', 'is_admin' => false]);

    // Lowercase query against a capitalised column: a plain LIKE would miss
    // this on Postgres.
    $response = asAdmin($token)->getJson('/api/v1/admin/customers?search=bopha');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$match->id]);
});

test('store creates a shopper with an address and the vip badge', function (): void {
    [, $token] = customerAdmin();

    $response = asAdmin($token)->postJson('/api/v1/admin/customers', [
        'name' => 'Sreymom Yun',
        'email' => 'sreymom@example.test',
        'phone' => '+85592100010',
        'address' => '18 Preah Sihanouk Blvd, Phnom Penh',
        'status' => 'vip',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'Sreymom')
        ->assertJsonPath('data.last_name', 'Yun')
        ->assertJsonPath('data.status', 'vip')
        ->assertJsonPath('data.address', '18 Preah Sihanouk Blvd, Phnom Penh')
        ->assertJsonPath('data.orders_count', 0);

    $created = User::query()->where('email', 'sreymom@example.test')->firstOrFail();

    expect($created->is_admin)->toBeFalse()
        ->and($created->is_active)->toBeTrue()
        ->and($created->customerGroups()->pluck('slug')->all())->toBe(['vip']);
});

test('an admin form address replaces the structured one so it reads back verbatim', function (): void {
    [, $token] = customerAdmin();

    $shopper = User::factory()->create(['is_admin' => false]);
    $shopper->addresses()->create([
        'type' => 'shipping',
        'full_name' => $shopper->name,
        'address_line_1' => '12 Street 240',
        'city' => 'Phnom Penh',
        'postal_code' => '12301',
        'country' => 'Cambodia',
        'is_default' => true,
        'is_active' => true,
    ]);

    $response = asAdmin($token)->patchJson('/api/v1/admin/customers/'.$shopper->id, [
        'name' => $shopper->name,
        'email' => $shopper->email,
        'address' => '9 Mondul 2, Siem Reap',
        'status' => 'active',
    ]);

    $response->assertOk()->assertJsonPath('data.address', '9 Mondul 2, Siem Reap');

    // Re-reading must not re-append the city/postcode that were there before.
    asAdmin($token)->getJson('/api/v1/admin/customers/'.$shopper->id)
        ->assertOk()
        ->assertJsonPath('data.address', '9 Mondul 2, Siem Reap');
});

test('setting a customer inactive deactivates without banning and drops the vip badge', function (): void {
    [, $token] = customerAdmin();

    $shopper = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $vip = CustomerGroup::create(['name' => 'VIP', 'slug' => 'vip', 'is_active' => true]);
    $shopper->customerGroups()->attach($vip->id);

    asAdmin($token)->patchJson('/api/v1/admin/customers/'.$shopper->id, [
        'name' => $shopper->name,
        'email' => $shopper->email,
        'status' => 'inactive',
    ])->assertOk()->assertJsonPath('data.status', 'inactive');

    $shopper->refresh();

    expect($shopper->is_active)->toBeFalse()
        ->and($shopper->is_banned)->toBeFalse()
        ->and($shopper->customerGroups()->count())->toBe(0);
});

test('a banned customer still reads as inactive', function (): void {
    [, $token] = customerAdmin();

    $shopper = User::factory()->create(['is_admin' => false, 'is_active' => true, 'is_banned' => true]);

    asAdmin($token)->getJson('/api/v1/admin/customers/'.$shopper->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

test('destroy soft-deletes the shopper', function (): void {
    [, $token] = customerAdmin();

    $shopper = User::factory()->create(['is_admin' => false]);

    asAdmin($token)->deleteJson('/api/v1/admin/customers/'.$shopper->id)->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $shopper->id]);
});

test('admin accounts are not reachable through the customer endpoints', function (): void {
    [$admin, $token] = customerAdmin();

    $otherAdmin = User::factory()->superAdmin()->create();

    asAdmin($token)->getJson('/api/v1/admin/customers/'.$otherAdmin->id)->assertNotFound();
    asAdmin($token)->patchJson('/api/v1/admin/customers/'.$otherAdmin->id, [
        'name' => 'Hijacked',
        'email' => 'hijacked@example.test',
        'status' => 'active',
    ])->assertNotFound();
    asAdmin($token)->deleteJson('/api/v1/admin/customers/'.$otherAdmin->id)->assertNotFound();

    expect(User::query()->whereKey($otherAdmin->id)->exists())->toBeTrue()
        ->and($admin->fresh()->is_admin)->toBeTrue();
});

test('writing customers requires the matching permission', function (): void {
    [$admin, $token] = customerAdmin();

    // Keep customers.view, drop the three write grants.
    $admin->syncRoles([]);
    $admin->givePermissionTo('customers.view');

    $shopper = User::factory()->create(['is_admin' => false]);

    asAdmin($token)->getJson('/api/v1/admin/customers')->assertOk();
    asAdmin($token)->postJson('/api/v1/admin/customers', [
        'name' => 'Nope',
        'email' => 'nope@example.test',
        'status' => 'active',
    ])->assertForbidden();
    asAdmin($token)->patchJson('/api/v1/admin/customers/'.$shopper->id, [
        'name' => 'Nope',
        'email' => 'nope@example.test',
        'status' => 'active',
    ])->assertForbidden();
    asAdmin($token)->deleteJson('/api/v1/admin/customers/'.$shopper->id)->assertForbidden();
});

test('email and phone must stay unique across customers', function (): void {
    [, $token] = customerAdmin();

    $existing = User::factory()->create(['is_admin' => false, 'phone' => '+85592100001']);

    asAdmin($token)->postJson('/api/v1/admin/customers', [
        'name' => 'Clash',
        'email' => $existing->email,
        'phone' => '+85592100001',
        'status' => 'active',
    ])->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
});

test('a blank phone is stored as null so two customers can both omit it', function (): void {
    [, $token] = customerAdmin();

    foreach (['first@example.test', 'second@example.test'] as $email) {
        asAdmin($token)->postJson('/api/v1/admin/customers', [
            'name' => 'No Phone',
            'email' => $email,
            'phone' => '',
            'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.phone', null);
    }

    expect(User::query()->whereIn('email', ['first@example.test', 'second@example.test'])->count())->toBe(2);
});
