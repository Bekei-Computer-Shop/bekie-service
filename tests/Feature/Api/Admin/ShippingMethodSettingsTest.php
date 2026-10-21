<?php

declare(strict_types=1);

use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

function shippingSettingsAdminToken(): string
{
    $user = User::factory()->superAdmin()->create();
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return (new AdminAuthService)->createAdminToken($user)['access_token'];
}

test('admin can manage checkout shipping methods through settings', function (): void {
    $headers = ['Authorization' => 'Bearer '.shippingSettingsAdminToken()];
    $payload = [
        'name' => 'Express delivery',
        'code' => 'express_delivery',
        'description' => 'Next day delivery',
        'base_price' => 8.5,
        'price_per_kg' => 1.25,
        'min_weight' => 0,
        'max_weight' => 20,
        'min_delivery_days' => 1,
        'max_delivery_days' => 2,
        'is_active' => true,
        'type' => 'courier',
        'sort_order' => 1,
    ];

    $created = $this->withHeaders($headers)
        ->postJson('/api/v1/admin/shipping-methods', $payload)
        ->assertCreated()
        ->assertJsonPath('data.code', 'express_delivery')
        ->assertJsonPath('data.is_active', true)
        ->json('data');

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/shipping-methods')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id']);

    $this->withHeaders($headers)
        ->patchJson('/api/v1/admin/shipping-methods/'.$created['id'], [
            ...$payload,
            'is_active' => false,
            'base_price' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.base_price', '10.00');

    $this->withHeaders($headers)
        ->deleteJson('/api/v1/admin/shipping-methods/'.$created['id'])
        ->assertNoContent();

    expect(ShippingMethod::withTrashed()->findOrFail($created['id'])->trashed())->toBeTrue();
});
