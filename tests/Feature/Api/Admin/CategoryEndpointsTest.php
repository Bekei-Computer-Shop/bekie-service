<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);

    $user = User::factory()->superAdmin()->create();

    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $tokens = (new AdminAuthService)->createAdminToken($user);

    $this->headers = ['Authorization' => 'Bearer '.$tokens['access_token']];
});

test('admin category listing includes the image thumbnail and display fields', function (): void {
    Category::create([
        'name' => 'Laptops',
        'slug' => 'laptops',
        'description' => 'Portable computers.',
        'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=400',
        'icon' => 'laptop',
        'is_active' => true,
        'is_featured' => true,
        'sort_order' => 1,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/categories')
        ->assertOk();

    $category = collect($response->json('data.data') ?? $response->json('data'))->firstWhere('slug', 'laptops');

    expect($category)->not->toBeNull()
        ->and($category['image'])->toBe('https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=400')
        ->and($category['icon'])->toBe('laptop')
        ->and($category['description'])->toBe('Portable computers.')
        ->and($category['is_featured'])->toBeTruthy();
});

test('admin category show includes the image thumbnail', function (): void {
    $category = Category::create([
        'name' => 'Monitors',
        'slug' => 'monitors',
        'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=400',
        'is_active' => true,
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/admin/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.image', 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=400');
});
