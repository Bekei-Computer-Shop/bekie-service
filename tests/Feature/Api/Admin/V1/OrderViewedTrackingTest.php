<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\AdminNotificationService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

function viewedTrackingToken(User $admin): string
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return (new AdminAuthService)->createAdminToken($admin)['access_token'];
}

function viewedTrackingAs(User $admin)
{
    return test()->withHeaders([
        'Authorization' => 'Bearer '.viewedTrackingToken($admin),
        'Accept' => 'application/json',
    ]);
}

/**
 * Places an order the way checkout does: the notification service tells every
 * active admin about it, which is the only thing "unviewed" is derived from.
 */
function viewedTrackingOrder(string $source = 'web'): Order
{
    $order = Order::create([
        'order_number' => Str::upper(Str::random(10)),
        'status' => 'pending',
        'currency' => 'USD',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal' => 10,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'grand_total' => 10,
    ]);

    app(AdminNotificationService::class)->newOrder($order, $source);

    return $order;
}

test('lists the uuids of orders this admin has not viewed yet', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $first = viewedTrackingOrder();
    $second = viewedTrackingOrder('app');

    $response = viewedTrackingAs($admin)->getJson('/api/v1/admin/orders/unviewed');

    $response->assertOk()->assertJsonPath('data.count', 2);
    expect($response->json('data.order_ids'))->toEqualCanonicalizing([$first->uuid, $second->uuid]);
});

test('viewing an order clears it for that admin and leaves other admins untouched', function (): void {
    $viewer = User::factory()->superAdmin()->create();
    $other = User::factory()->superAdmin()->create();
    $viewed = viewedTrackingOrder();
    $untouched = viewedTrackingOrder();

    viewedTrackingAs($viewer)
        ->postJson("/api/v1/admin/orders/{$viewed->uuid}/viewed")
        ->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.order_ids', [$untouched->uuid]);

    viewedTrackingAs($viewer)->getJson('/api/v1/admin/orders/unviewed')->assertJsonPath('data.count', 1);
    viewedTrackingAs($other)->getJson('/api/v1/admin/orders/unviewed')->assertJsonPath('data.count', 2);
});

test('an order can be marked viewed by its numeric id as well as its uuid', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $order = viewedTrackingOrder();

    // The Notifications page links orders by the id in the notification payload.
    viewedTrackingAs($admin)
        ->postJson("/api/v1/admin/orders/{$order->id}/viewed")
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

test('marking an order viewed twice is harmless', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $order = viewedTrackingOrder();

    viewedTrackingAs($admin)->postJson("/api/v1/admin/orders/{$order->uuid}/viewed")->assertOk();
    viewedTrackingAs($admin)
        ->postJson("/api/v1/admin/orders/{$order->uuid}/viewed")
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

test('notifications that are not about orders are neither counted nor cleared', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $order = viewedTrackingOrder();
    app(AdminNotificationService::class)->newCustomer(User::factory()->create(['is_admin' => false]));

    viewedTrackingAs($admin)->getJson('/api/v1/admin/orders/unviewed')->assertJsonPath('data.count', 1);

    $unreadBefore = $admin->unreadNotifications()->count();
    viewedTrackingAs($admin)->postJson("/api/v1/admin/orders/{$order->uuid}/viewed")->assertOk();

    // Only the order's own notification was marked read; the customer one stays.
    expect($admin->unreadNotifications()->count())->toBe($unreadBefore - 1);
});

test('a deleted order is no longer counted as unviewed', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $kept = viewedTrackingOrder();
    $deleted = viewedTrackingOrder();
    $deleted->delete();

    $response = viewedTrackingAs($admin)->getJson('/api/v1/admin/orders/unviewed');

    $response->assertOk()->assertJsonPath('data.count', 1);
    expect($response->json('data.order_ids'))->toBe([$kept->uuid]);
});

test('mark all as read on the notifications page also clears unviewed orders', function (): void {
    $admin = User::factory()->superAdmin()->create();
    viewedTrackingOrder();
    viewedTrackingOrder();

    viewedTrackingAs($admin)->postJson('/api/v1/admin/notifications/read-all')->assertOk();

    viewedTrackingAs($admin)->getJson('/api/v1/admin/orders/unviewed')->assertJsonPath('data.count', 0);
});

test('a role without orders.view cannot read or change unviewed state', function (): void {
    $staff = User::factory()->create(['is_admin' => true, 'role' => 'staff']);
    $staff->syncRoles(['staff']);
    $order = viewedTrackingOrder();

    viewedTrackingAs($staff)->getJson('/api/v1/admin/orders/unviewed')->assertForbidden();
    viewedTrackingAs($staff)->postJson("/api/v1/admin/orders/{$order->uuid}/viewed")->assertForbidden();
});

test('marking an order that does not exist viewed is a 404 and changes nothing', function (): void {
    $admin = User::factory()->superAdmin()->create();
    viewedTrackingOrder();

    viewedTrackingAs($admin)
        ->postJson('/api/v1/admin/orders/'.Str::uuid().'/viewed')
        ->assertNotFound();

    // Asserted through the real endpoint so a missing route cannot pass for a 404.
    viewedTrackingAs($admin)->getJson('/api/v1/admin/orders/unviewed')->assertJsonPath('data.count', 1);
});
