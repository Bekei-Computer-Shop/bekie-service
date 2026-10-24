<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function adminActivityLogAuthedUser(array $attrs = []): array
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

test('admin list and detail reads do not create activity logs', function (): void {
    [, $token] = adminActivityLogAuthedUser();
    $product = Product::factory()->create();

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/products')
        ->assertOk();

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/products/'.$product->id)
        ->assertOk();

    $this->assertDatabaseCount('activity_logs', 0);
});

test('admin can filter activity logs by actor action and date range', function (): void {
    [$actor, $token] = adminActivityLogAuthedUser();

    $target = User::factory()->create();

    ActivityLog::create([
        'user_id' => $actor->id,
        'action' => 'created',
        'target_type' => User::class,
        'target_id' => $target->id,
        'ip_address' => '203.0.113.1',
        'user_agent' => 'Mozilla/5.0',
        'created_at' => now()->subDay(),
    ]);

    ActivityLog::create([
        'user_id' => $actor->id,
        'action' => 'updated',
        'target_type' => User::class,
        'target_id' => $target->id,
        'ip_address' => '203.0.113.2',
        'user_agent' => 'Mozilla/5.0',
        'created_at' => now(),
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/activity-logs?user_id='.$actor->id.'&action=updated&date_from='.now()->subDay()->toDateString().'&date_to='.now()->toDateString());

    $response->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.action', 'updated')
        ->assertJsonPath('data.items.0.actor.id', $actor->id)
        ->assertJsonPath('data.items.0.target.id', $target->id);
});

test('admin product updates log the authenticated actor and bound product target once', function (): void {
    [$actor, $token] = adminActivityLogAuthedUser();
    $product = Product::factory()->create();

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Updated product name',
        ])
        ->assertOk();

    $this->assertDatabaseCount('activity_logs', 1);

    $log = ActivityLog::query()->firstOrFail();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->action)->toBe('updated')
        ->and($log->target_type)->toBe('Product')
        ->and($log->target_key)->toBe($product->id);

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/activity-logs?target_type=Product')
        ->assertOk()
        ->assertJsonPath('data.items.0.target.id', $product->id);
});
