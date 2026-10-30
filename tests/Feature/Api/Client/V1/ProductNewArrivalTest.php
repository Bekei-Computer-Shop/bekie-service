<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['products.new_arrival_days' => 2]);
    $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'UTC'));
});

/** An active product whose first publish was at $publishedAt. */
function productPublishedAt(?string $publishedAt, array $attributes = []): Product
{
    $product = Product::factory()->create(['is_active' => true, ...$attributes]);
    // Query update: bypasses the saving hook, so a null stays null (legacy rows).
    Product::whereKey($product->id)->update(['published_at' => $publishedAt]);

    return $product->refresh();
}

/** @return array<string, mixed> `is_new_arrival` per product id from the list API. */
function newArrivalFlags(array $query = []): array
{
    $response = test()->getJson('/api/v1/products?'.http_build_query($query))->assertOk();

    return collect($response->json('data'))->pluck('is_new_arrival', 'id')->all();
}

test('the product list flags new arrivals by publish date, boundary included', function (): void {
    $justNow = productPublishedAt('2026-10-30 09:00:00');
    $oneDay = productPublishedAt('2026-10-29 10:00:00');
    $exactlyTwoDays = productPublishedAt('2026-10-28 10:00:00');
    $older = productPublishedAt('2026-10-28 09:00:00');
    $future = productPublishedAt('2026-10-31 10:00:00');
    $neverStamped = productPublishedAt(null);

    $flags = newArrivalFlags();

    expect($flags[$justNow->id])->toBeTrue()
        ->and($flags[$oneDay->id])->toBeTrue()
        ->and($flags[$exactlyTwoDays->id])->toBeTrue()
        ->and($flags[$older->id])->toBeFalse()
        ->and($flags[$future->id])->toBeFalse()
        ->and($flags[$neverStamped->id])->toBeFalse();
});

test('the list returns the publish date next to the flag', function (): void {
    $product = productPublishedAt('2026-10-30 09:00:00');

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.published_at', '2026-10-30T09:00:00+00:00')
        ->assertJsonPath('data.0.is_new_arrival', true);
});

test('an unpublished product is not a new arrival and has no publish date', function (): void {
    $product = Product::factory()->inactive()->create();

    expect($product->published_at)->toBeNull()
        ->and($product->isNewArrival())->toBeFalse();
});

test('a product deactivated after publishing is not a new arrival', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    $product->update(['is_active' => false]);

    expect($product->refresh()->isNewArrival())->toBeFalse();
});

test('the publish date is stamped the first time a product goes live', function (): void {
    $product = Product::factory()->inactive()->create();

    $this->travelTo(Carbon::parse('2026-10-31 08:00:00', 'UTC'));
    $product->update(['is_active' => true]);

    expect($product->refresh()->published_at->toIso8601String())->toBe('2026-10-31T08:00:00+00:00')
        ->and($product->isNewArrival())->toBeTrue();
});

test('editing a product does not restart its new-arrival period', function (): void {
    $product = productPublishedAt('2026-10-25 10:00:00');

    $product->update(['name' => 'Renamed', 'price' => 99]);
    $product->update(['is_active' => false]);
    $product->update(['is_active' => true]);

    expect($product->refresh()->published_at->toIso8601String())->toBe('2026-10-25T10:00:00+00:00')
        ->and($product->isNewArrival())->toBeFalse();
});

test('adding a variant does not restart the new-arrival period', function (): void {
    $product = productPublishedAt('2026-10-25 10:00:00');

    ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'New colour',
        'slug' => 'new-colour',
        'sku' => 'NEW-COLOUR',
        'price' => 10,
        'is_active' => true,
    ]);

    expect($product->refresh()->published_at->toIso8601String())->toBe('2026-10-25T10:00:00+00:00')
        ->and(newArrivalFlags()[$product->id])->toBeFalse();
});

test('the period comes from configuration', function (): void {
    $product = productPublishedAt('2026-10-10 10:00:00');
    expect($product->isNewArrival())->toBeFalse();

    config(['products.new_arrival_days' => 30]);

    expect($product->isNewArrival())->toBeTrue()
        ->and(newArrivalFlags()[$product->id])->toBeTrue();
});

test('pagination and filters still work and carry the flag', function (): void {
    foreach (range(1, 3) as $i) {
        productPublishedAt('2026-10-30 09:00:00', ['name' => "Laptop {$i}", 'sort_order' => $i]);
    }
    productPublishedAt('2026-10-01 09:00:00', ['name' => 'Mouse', 'sort_order' => 9]);

    $page = $this->getJson('/api/v1/products?per_page=2&page=2')->assertOk();
    expect($page->json('data'))->toHaveCount(2);

    $search = newArrivalFlags(['search' => 'Laptop']);
    expect($search)->toHaveCount(3)
        ->and(array_values(array_unique($search)))->toBe([true]);

    expect(newArrivalFlags(['search' => 'Mouse']))->toBe([Product::where('name', 'Mouse')->value('id') => false]);
});
