<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function adminAuthedUserForPermissions(): array
{
    $user = User::factory()->superAdmin()->create();
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $tokens = (new AdminAuthService)->createAdminToken($user);

    return [$user, $tokens['access_token']];
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('admin with permissions.view can list permissions', function (): void {
    [, $token] = adminAuthedUserForPermissions();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/permissions');

    $response->assertOk()->assertJsonStructure(['data' => ['items', 'pagination']]);
});

test('admin can create a permission', function (): void {
    [, $token] = adminAuthedUserForPermissions();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/permissions', [
            'name' => 'reports.view',
            'guard_name' => 'api',
        ]);

    $response->assertCreated()->assertJsonPath('data.name', 'reports.view');
    expect(Permission::where('name', 'reports.view')->where('guard_name', 'api')->exists())->toBeTrue();
});

test('permission name uniqueness is enforced', function (): void {
    [, $token] = adminAuthedUserForPermissions();
    Permission::create(['name' => 'reports.view', 'guard_name' => 'api']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/permissions', [
            'name' => 'reports.view',
            'guard_name' => 'api',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('admin can update a permission name', function (): void {
    [, $token] = adminAuthedUserForPermissions();
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'api']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->patchJson('/api/v1/admin/permissions/'.$permission->id, [
            'name' => 'reports.export',
        ]);

    $response->assertOk()->assertJsonPath('data.name', 'reports.export');
});

test('admin can soft-delete a permission', function (): void {
    [, $token] = adminAuthedUserForPermissions();
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'api']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->deleteJson('/api/v1/admin/permissions/'.$permission->id);

    $response->assertNoContent();
    expect($permission->fresh()->deleted_at)->not->toBeNull();
});

test('invalid permission name format is rejected', function (): void {
    [, $token] = adminAuthedUserForPermissions();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/permissions', [
            'name' => 'Reports.View',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('non-existent permission returns 404 on update', function (): void {
    [, $token] = adminAuthedUserForPermissions();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->patchJson('/api/v1/admin/permissions/999999', [
            'name' => 'reports.view',
        ]);

    $response->assertNotFound();
});
