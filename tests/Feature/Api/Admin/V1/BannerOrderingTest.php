<?php

declare(strict_types=1);

use App\Models\Banner;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bannerToken(User $user): string
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    return (new AdminAuthService)->createAdminToken($user)['access_token'];
}

function superAdminBannerToken(): string
{
    return bannerToken(User::factory()->superAdmin()->create());
}

function makeBanner(string $title, int $sortOrder, array $attributes = []): Banner
{
    return Banner::create(array_merge([
        'title' => $title,
        'sort_order' => $sortOrder,
        'position' => 'homepage',
        'is_active' => true,
    ], $attributes));
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('the banner list comes back in carousel order, not by recency', function (): void {
    // Created newest-first so `latest()` and `sort_order` disagree: under the
    // old ordering this list would come back exactly reversed.
    makeBanner('Third', 3);
    makeBanner('Second', 2);
    makeBanner('First', 1);

    $response = $this->withHeader('Authorization', 'Bearer '.superAdminBannerToken())
        ->getJson('/api/v1/admin/banners')
        ->assertOk();

    expect(array_column($response->json('data'), 'title'))
        ->toBe(['First', 'Second', 'Third']);
});

test('banners with the same sort order fall back to a stable id tiebreak', function (): void {
    $a = makeBanner('Alpha', 0);
    $b = makeBanner('Bravo', 0);

    $response = $this->withHeader('Authorization', 'Bearer '.superAdminBannerToken())
        ->getJson('/api/v1/admin/banners')
        ->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$a->id, $b->id]);
});

test('a sort_order-only update leaves every other column alone', function (): void {
    // The admin reorder sends nothing but sort_order, so a partial payload must
    // not blank out the content columns it omits.
    $banner = makeBanner('Keep My Content', 5, [
        'subtitle' => 'Still here',
        'image_desktop' => 'https://cdn.example/cover.png',
        'button_text' => 'Shop Now',
        'button_url' => '/deals',
        'meta' => ['frames' => ['https://cdn.example/f2.png'], 'durationMs' => 5000],
    ]);

    $this->withHeader('Authorization', 'Bearer '.superAdminBannerToken())
        ->patchJson("/api/v1/admin/banners/{$banner->id}", ['sort_order' => 1])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1)
        ->assertJsonPath('data.title', 'Keep My Content')
        ->assertJsonPath('data.subtitle', 'Still here')
        ->assertJsonPath('data.image_desktop', 'https://cdn.example/cover.png')
        ->assertJsonPath('data.button_text', 'Shop Now')
        ->assertJsonPath('data.button_url', '/deals')
        ->assertJsonPath('data.meta.frames.0', 'https://cdn.example/f2.png')
        ->assertJsonPath('data.meta.durationMs', 5000);
});

test('reordering requires banners.update', function (): void {
    $banner = makeBanner('Locked', 1);

    $user = User::factory()->create(['is_admin' => true]);

    $this->withHeader('Authorization', 'Bearer '.bannerToken($user))
        ->patchJson("/api/v1/admin/banners/{$banner->id}", ['sort_order' => 2])
        ->assertForbidden();
});
