<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function soldProductsReportAuthedUser(array $attrs = []): array
{
    $user = User::factory()->superAdmin()->create($attrs);

    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    $tokens = (new AdminAuthService)->createAdminToken($user);

    return [$user, $tokens['access_token']];
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('admin can fetch sold products report with chart-ready aggregation', function (): void {
    [$admin, $token] = soldProductsReportAuthedUser();

    $productA = Product::factory()->create(['name' => 'Keyboard']);
    $productB = Product::factory()->create(['name' => 'Mouse']);

    $orderOne = Order::create([
        'order_number' => 'ORD-1001',
        'user_id' => $admin->id,
        'status' => 'delivered',
        'grand_total' => 200,
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ]);

    OrderItem::create([
        'order_id' => $orderOne->id,
        'product_id' => $productA->id,
        'quantity' => 2,
        'unit_price' => 50,
        'sale_price' => 50,
        'subtotal' => 100,
        'discount' => 0,
        'tax' => 0,
        'total' => 100,
        'product_name' => $productA->name,
        'product_sku' => $productA->sku,
        'status' => 'delivered',
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ]);

    $orderTwo = Order::create([
        'order_number' => 'ORD-1002',
        'user_id' => $admin->id,
        'status' => 'delivered',
        'grand_total' => 120,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $orderTwo->id,
        'product_id' => $productA->id,
        'quantity' => 1,
        'unit_price' => 60,
        'sale_price' => 60,
        'subtotal' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 60,
        'product_name' => $productA->name,
        'product_sku' => $productA->sku,
        'status' => 'delivered',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $orderTwo->id,
        'product_id' => $productB->id,
        'quantity' => 3,
        'unit_price' => 20,
        'sale_price' => 20,
        'subtotal' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 60,
        'product_name' => $productB->name,
        'product_sku' => $productB->sku,
        'status' => 'delivered',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/reports/sold-products?preset=monthly&date_from='.now()->subMonths(2)->toDateString().'&date_to='.now()->toDateString());

    $response->assertOk()
        ->assertJsonPath('data.labels.0', now()->format('Y-m'))
        ->assertJsonPath('data.series.0.name', 'Keyboard')
        ->assertJsonPath('data.series.0.data.0', 3)
        ->assertJsonPath('data.series.0.revenue_data.0', 160)
        ->assertJsonPath('data.series.1.name', 'Mouse')
        ->assertJsonPath('data.series.1.data.0', 3)
        ->assertJsonPath('data.series.1.revenue_data.0', 60);
});
