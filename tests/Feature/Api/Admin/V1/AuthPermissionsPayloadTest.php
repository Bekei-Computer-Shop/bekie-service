<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/*
 * The admin portal gates every sidebar item, route and action button on
 * `user.permissions` from these two endpoints (see BackieDealFrontEnd
 * `src/lib/permissions.js` — a missing list denies everything, deliberately, so
 * a session persisted before the API carried permissions cannot read as full
 * access). Both payloads must therefore carry the caller's flattened grants.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

function adminTokenFor(User $user): string
{
    // createAdminToken() reads request()->getHost() for the `aud` claim.
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return (new AdminAuthService)->createAdminToken($user)['access_token'];
}

test('login returns the caller permissions', function (): void {
    $user = User::factory()->superAdmin()->create();

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $permissions = $response->json('data.user.permissions');

    expect($permissions)->toBeArray()->not->toBeEmpty()
        ->and($permissions)->toContain('orders.view', 'products.view');
});

test('auth/me returns the caller permissions', function (): void {
    $user = User::factory()->superAdmin()->create();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.adminTokenFor($user)])
        ->getJson('/api/v1/admin/auth/me')
        ->assertOk();

    $permissions = $response->json('data.permissions');

    expect($permissions)->toBeArray()->not->toBeEmpty()
        ->and($permissions)->toContain('orders.view', 'administrators.view');
});

test('auth/me scopes permissions to the caller role', function (): void {
    // is_admin is what AuthenticateAdminApiToken checks; the role decides grants.
    $manager = User::factory()->create(['is_admin' => true, 'role' => 'manager']);
    $manager->assignRole('manager');

    $response = $this->withHeaders(['Authorization' => 'Bearer '.adminTokenFor($manager)])
        ->getJson('/api/v1/admin/auth/me')
        ->assertOk();

    $permissions = $response->json('data.permissions');

    expect($permissions)->toContain('orders.view', 'stock.view')
        // Manager is not an administrator manager: the portal must not offer it.
        ->and($permissions)->not->toContain('administrators.view');
});
