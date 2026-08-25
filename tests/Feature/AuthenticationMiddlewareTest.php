<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\AuthService;
use App\Services\JwtService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('a valid client JWT resolves its user through api_tokens', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $request = Request::create('/api/v1/carts', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $token = (new AuthService)->createToken($user, $request)['access_token'];

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/carts')
        ->assertOk();
});

test('client JWT middleware rejects missing invalid and expired tokens', function (): void {
    $this->getJson('/api/v1/carts')->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer not-a-jwt')
        ->getJson('/api/v1/carts')
        ->assertUnauthorized();

    $expired = (new JwtService)->encode([
        'sub' => '1',
        'jti' => Str::random(64),
        'scope' => 'client',
    ], -1);

    $this->withHeader('Authorization', 'Bearer '.$expired)
        ->getJson('/api/v1/carts')
        ->assertUnauthorized();
});

test('public health endpoint does not require a JWT', function (): void {
    $this->getJson('/api/health')->assertOk();
});

test('admin routes distinguish missing tokens from missing permissions', function (): void {
    $this->getJson('/api/v1/admin/auth/me')->assertUnauthorized();

    $staffAdmin = User::factory()->create(['is_admin' => true]);
    $staffAdmin->assignRole('staff');
    $staffToken = (new AdminAuthService)->createAdminToken($staffAdmin)['access_token'];

    // Users is the example of a route staff is denied. It used to be
    // POST /admin/media, but staff holds media.create now that the role is
    // scoped to its capability list — that request reaches validation and
    // answers 422, which would no longer prove anything about the guard.
    $this->withHeader('Authorization', 'Bearer '.$staffToken)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();

    $superAdmin = User::factory()->superAdmin()->create();
    $superAdminToken = (new AdminAuthService)->createAdminToken($superAdmin)['access_token'];

    $this->withHeader('Authorization', 'Bearer '.$superAdminToken)
        ->getJson('/api/v1/admin/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $superAdmin->id);
});
