<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('admin promotions manage storefront coupons', function (): void {
    $user = User::factory()->superAdmin()->create();
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $tokens = (new AdminAuthService)->createAdminToken($user);
    $headers = ['Authorization' => 'Bearer '.$tokens['access_token']];

    $payload = [
        'name' => 'Summer sale',
        'code' => 'SUMMER20',
        'type' => 'percentage',
        'value' => 20,
        'min_order_amount' => 50,
        'starts_at' => now()->toDateString(),
        'expires_at' => now()->addWeek()->toDateString(),
        'usage_limit' => 100,
        'user_limit' => 1,
        'is_active' => true,
        'description' => 'Seasonal promotion',
    ];

    $created = $this->withHeaders($headers)
        ->postJson('/api/v1/admin/promotions', $payload)
        ->assertCreated()
        ->assertJsonPath('data.code', 'SUMMER20');

    $couponId = $created->json('data.id');

    $this->assertDatabaseHas('coupons', ['id' => $couponId, 'code' => 'SUMMER20']);
    $this->assertDatabaseMissing('promotions', ['code' => 'SUMMER20']);

    $this->withHeaders($headers)
        ->getJson("/api/v1/admin/promotions/{$couponId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Summer sale');

    $this->withHeaders($headers)
        ->patchJson("/api/v1/admin/promotions/{$couponId}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/promotions')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'SUMMER20');

    $this->withHeaders($headers)
        ->deleteJson("/api/v1/admin/promotions/{$couponId}")
        ->assertNoContent();

    expect(Coupon::find($couponId))->toBeNull();
});
