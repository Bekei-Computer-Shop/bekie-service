<?php

declare(strict_types=1);

use App\Models\ActivityLog;
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
        ->assertJsonPath('data.items.0.actor.id', $actor->id);
});
