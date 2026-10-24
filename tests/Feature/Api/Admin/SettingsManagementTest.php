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

function settingsManagementAdminToken(): string
{
    $user = User::factory()->superAdmin()->create();
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return (new AdminAuthService)->createAdminToken($user)['access_token'];
}

test('settings sections persist nested fields and arrays through the API', function (): void {
    $headers = ['Authorization' => 'Bearer '.settingsManagementAdminToken()];
    $settings = [
        'general' => [
            'logo_url' => 'https://cdn.example.test/store/logo.png',
            'favicon_url' => 'https://cdn.example.test/store/favicon.png',
        ],
        'shipping' => [
            'zones' => [['name' => 'City', 'regions' => 'Phnom Penh', 'methods' => 'Express', 'rates' => '$5']],
            'delivery' => [['id' => 'express', 'enabled' => true]],
            'warehouse' => 'Central depot',
            'package_weight' => 1.5,
            'free_shipping_threshold' => 100,
        ],
        'taxes' => [
            'rates' => [['region' => 'Cambodia', 'name' => 'VAT', 'rate' => 10, 'applies_to' => 'All goods']],
        ],
        'notifications' => [
            'rows' => [['name' => 'New order', 'email' => true, 'in_app' => true, 'sms' => false]],
            'recipients' => ['ops@example.test'],
        ],
        'integrations' => [
            'cards' => [['id' => 'analytics', 'enabled' => true, 'status' => 'Enabled']],
            'webhooks' => [['endpoint' => 'https://example.test/hook', 'events' => 'orders.created', 'status' => 'Saved']],
        ],
    ];

    $this->withHeaders($headers)
        ->patchJson('/api/v1/admin/settings', ['settings' => $settings])
        ->assertOk()
        ->assertJsonPath('data.settings.general.logo_url', $settings['general']['logo_url'])
        ->assertJsonPath('data.settings.shipping.zones.0.name', 'City')
        ->assertJsonPath('data.settings.taxes.rates.0.applies_to', 'All goods')
        ->assertJsonPath('data.settings.notifications.recipients.0', 'ops@example.test')
        ->assertJsonPath('data.settings.integrations.webhooks.0.status', 'Saved');

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonPath('data.settings.shipping.warehouse', 'Central depot')
        ->assertJsonPath('data.settings.notifications.rows.0.in_app', true);
});

test('settings API rejects fields that are not in the declared section contract', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer '.settingsManagementAdminToken()])
        ->patchJson('/api/v1/admin/settings', [
            'settings' => ['shipping' => ['unknown_rate_table' => []]],
        ])
        ->assertUnprocessable();
});

test('team administrator listing includes all admin accounts and honors per page', function (): void {
    $headers = ['Authorization' => 'Bearer '.settingsManagementAdminToken()];
    User::factory()->create(['is_admin' => true]);

    $this->withHeaders($headers)
        ->getJson('/api/v1/admin/administrators?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonCount(1, 'data.items');
});
