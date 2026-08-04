<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('core admin feature endpoints are available', function (): void {
    $user = User::factory()->superAdmin()->create();

    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $tokens = (new AdminAuthService)->createAdminToken($user);

    $headers = ['Authorization' => 'Bearer '.$tokens['access_token']];

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/promotions')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/content')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/customers')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/orders')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/administrators')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/logs/visitors')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/logs/team')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/banners')
        ->assertOk();
});
