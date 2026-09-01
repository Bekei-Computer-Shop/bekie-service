<?php

declare(strict_types=1);

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The public storefront slides endpoint is the read-only counterpart of the
 * admin "Content → Homepage Slides" surface: the same `banners` rows, but only
 * the ones a visitor can currently see, in carousel order.
 */
function clientSlide(array $attributes = []): Banner
{
    return Banner::create(array_merge([
        'title' => 'Promo Banner',
        'subtitle' => 'Subtitle',
        'image_desktop' => 'https://cdn.example/slide-cover.jpg',
        'button_text' => 'Shop Now',
        'button_url' => '/deals',
        'position' => 'homepage',
        'is_active' => true,
        'sort_order' => 0,
        'meta' => [
            'frames' => ['https://cdn.example/frame-2.jpg'],
            'durationMs' => 5000,
            'transition' => 'fade',
            'gradient' => 'linear-gradient(135deg, #1b2a4a 0%, #6d28d9 100%)',
        ],
    ], $attributes));
}

test('client slides come back in carousel order, not insertion order', function (): void {
    clientSlide(['title' => 'Third', 'sort_order' => 3]);
    clientSlide(['title' => 'First', 'sort_order' => 1]);
    clientSlide(['title' => 'Second', 'sort_order' => 2]);

    $this->getJson('/api/v1/slides')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second')
        ->assertJsonPath('data.2.title', 'Third');
});

test('inactive, not-yet-started and expired slides are hidden from clients', function (): void {
    clientSlide(['title' => 'Hidden inactive', 'is_active' => false]);
    clientSlide(['title' => 'Hidden upcoming', 'starts_at' => now()->addDay()]);
    clientSlide(['title' => 'Hidden expired', 'ends_at' => now()->subDay()]);
    clientSlide(['title' => 'Visible', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    clientSlide(['title' => 'Always visible', 'starts_at' => null, 'ends_at' => null]);

    $this->getJson('/api/v1/slides')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Visible')
        ->assertJsonPath('data.1.title', 'Always visible');
});

test('the position query narrows the slides returned', function (): void {
    clientSlide(['title' => 'Homepage slide', 'position' => 'homepage']);
    clientSlide(['title' => 'Product page slide', 'position' => 'product_page']);

    $this->getJson('/api/v1/slides?position=product_page')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Product page slide');
});

test('a slide composes the cover image and meta frames into one ordered images array', function (): void {
    clientSlide([
        'image_desktop' => 'https://cdn.example/cover.jpg',
        'image_mobile' => 'https://cdn.example/mobile.jpg',
        'meta' => [
            'frames' => ['https://cdn.example/frame-2.jpg', 'https://cdn.example/frame-3.jpg'],
            'durationMs' => 5000,
            'transition' => 'fade',
        ],
    ]);

    $this->getJson('/api/v1/slides')
        ->assertOk()
        ->assertJsonPath('data.0.images.0', 'https://cdn.example/cover.jpg')
        ->assertJsonPath('data.0.images.1', 'https://cdn.example/frame-2.jpg')
        ->assertJsonPath('data.0.images.2', 'https://cdn.example/frame-3.jpg')
        ->assertJsonPath('data.0.image_mobile', 'https://cdn.example/mobile.jpg')
        ->assertJsonPath('data.0.duration_ms', 5000)
        ->assertJsonPath('data.0.transition', 'fade')
        ->assertJsonPath('data.0.button_url', '/deals');
});