<?php

declare(strict_types=1);

use App\Models\ContentItem;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function contentToken(): string
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    return (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('the content list is paged and reports pagination metadata', function (): void {
    ContentItem::factory()->count(20)->news()->create();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.contentToken()])
        ->getJson('/api/v1/admin/content?type=news&page=1&per_page=15')
        ->assertOk();

    $response->assertJsonPath('data.pagination.total', 20)
        ->assertJsonPath('data.pagination.per_page', 15)
        ->assertJsonPath('data.pagination.current_page', 1)
        ->assertJsonPath('data.pagination.last_page', 2)
        ->assertJsonCount(15, 'data.items');

    $this->withHeaders(['Authorization' => 'Bearer '.contentToken()])
        ->getJson('/api/v1/admin/content?type=news&page=2&per_page=15')
        ->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonCount(5, 'data.items');
});

test('per_page is clamped to a sane maximum', function (): void {
    ContentItem::factory()->count(3)->page()->create();

    $this->withHeaders(['Authorization' => 'Bearer '.contentToken()])
        ->getJson('/api/v1/admin/content?type=page&per_page=9999')
        ->assertOk()
        ->assertJsonPath('data.pagination.per_page', 100);
});

test('the type and status filters keep the two screens apart', function (): void {
    ContentItem::factory()->news()->published()->create();
    ContentItem::factory()->news()->create(['status' => 'draft']);
    ContentItem::factory()->page()->create(['status' => 'draft']);

    $this->withHeaders(['Authorization' => 'Bearer '.contentToken()])
        ->getJson('/api/v1/admin/content?type=news&status=draft')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.type', 'news')
        ->assertJsonPath('data.items.0.status', 'draft');
});

test('news rows carry the category and cover image through the API', function (): void {
    $token = contentToken();

    $created = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/content', [
            'type' => 'news',
            'title' => 'RTX 50-Series In Stock',
            'body' => 'Launch stock has landed.',
            'category' => 'Product News',
            'image_url' => 'https://picsum.photos/seed/rtx50/1200/630',
            'status' => 'draft',
        ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'Product News')
        ->assertJsonPath('data.image_url', 'https://picsum.photos/seed/rtx50/1200/630');

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/content/'.$created->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.category', 'Product News');
});
