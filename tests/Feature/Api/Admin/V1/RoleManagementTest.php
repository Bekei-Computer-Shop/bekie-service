<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function adminAuthedUserForRoles(): array
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

test('admin with roles.view can list roles', function (): void {
    [, $token] = adminAuthedUserForRoles();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/roles');

    $response->assertOk()->assertJsonStructure(['data' => ['items', 'pagination']]);
});

test('admin can create a role with permissions', function (): void {
    [, $token] = adminAuthedUserForRoles();

    $permId = Permission::where('name', 'users.view')->value('id');

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/roles', [
            'name' => 'editor',
            'guard_name' => 'api',
            'permissions' => [$permId],
        ]);

    $response->assertCreated()->assertJsonPath('data.name', 'editor');
    $role = Role::where('name', 'editor')->where('guard_name', 'api')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('users.view'))->toBeTrue();
});

test('role name uniqueness is enforced within api guard', function (): void {
    [, $token] = adminAuthedUserForRoles();
    Role::create(['name' => 'editor', 'guard_name' => 'api']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/roles', [
            'name' => 'editor',
            'guard_name' => 'api',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('platform roles cannot be deleted', function (): void {
    [, $token] = adminAuthedUserForRoles();
    $admin = Role::where('name', 'admin')->where('guard_name', 'api')->first();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->deleteJson('/api/v1/admin/roles/'.$admin->id);

    $response->assertStatus(422);
    expect($admin->fresh()->deleted_at)->toBeNull();
});

test('admin can sync role permissions', function (): void {
    [, $token] = adminAuthedUserForRoles();
    $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);
    $viewUsers = Permission::where('name', 'users.view')->first();
    $viewRoles = Permission::where('name', 'roles.view')->first();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/roles/'.$role->id.'/permissions', [
            'permissions' => [$viewUsers->id, $viewRoles->id],
        ]);

    $response->assertOk();
    $role->refresh();
    expect($role->hasPermissionTo('users.view'))->toBeTrue();
    expect($role->hasPermissionTo('roles.view'))->toBeTrue();
    expect($role->hasPermissionTo('users.create'))->toBeFalse();
});

test('invalid role name format is rejected', function (): void {
    [, $token] = adminAuthedUserForRoles();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/roles', [
            'name' => 'Editor With Spaces',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});
