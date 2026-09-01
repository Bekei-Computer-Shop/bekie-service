<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(RefreshDatabase::class);

function galleryToken(): string
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    return (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];
}

function asGalleryAdmin(): TestCase
{
    return test()->withHeader('Authorization', 'Bearer '.galleryToken());
}

function galleryProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'category_id' => Category::factory()->create()->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-SKU',
        'price' => 10.00,
        'stock_quantity' => 5,
        'is_active' => true,
    ], $attributes));
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('show returns the gallery in sort order', function (): void {
    $product = galleryProduct();

    // Inserted out of order on purpose: without the ordering on the relation
    // these come back by insertion id and the assertion below flips.
    foreach ([['c.jpg', 2], ['a.jpg', 0], ['b.jpg', 1]] as [$file, $sortOrder]) {
        ProductImage::create([
            'product_id' => $product->id,
            'image' => 'https://cdn.example.test/'.$file,
            'is_primary' => $file === 'a.jpg',
            'sort_order' => $sortOrder,
        ]);
    }

    $response = asGalleryAdmin()
        ->getJson('/api/v1/admin/products/'.$product->uuid)
        ->assertOk();

    expect(array_column($response->json('data.images'), 'image'))->toBe([
        'https://cdn.example.test/a.jpg',
        'https://cdn.example.test/b.jpg',
        'https://cdn.example.test/c.jpg',
    ]);
    expect($response->json('data.images.0.is_primary'))->toBeTrue();
});

test('an absolute image url is passed through rather than resolved against the disk', function (): void {
    $product = galleryProduct();
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://res.cloudinary.com/demo/image/upload/v1/products/a.jpg',
    ]);

    $response = asGalleryAdmin()->getJson('/api/v1/admin/products/'.$product->uuid)->assertOk();

    expect($response->json('data.images.0.url'))
        ->toBe('https://res.cloudinary.com/demo/image/upload/v1/products/a.jpg');
});

test('store persists a nested gallery and derives the thumbnail from the primary image', function (): void {
    $response = asGalleryAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Gallery Product',
        'sku' => 'GAL-1',
        'price' => 25.00,
        'images' => [
            ['image' => 'https://cdn.example.test/one.jpg'],
            ['image' => 'https://cdn.example.test/two.jpg', 'is_primary' => true],
        ],
    ])->assertCreated();

    expect($response->json('data.images'))->toHaveCount(2);
    // No explicit thumbnail was sent, so the primary row supplies it.
    expect($response->json('data.thumbnail'))->toBe('https://cdn.example.test/two.jpg');
    expect($response->json('data.images.1.is_primary'))->toBeTrue();
    expect($response->json('data.images.0.is_primary'))->toBeFalse();
    // Position falls through to sort_order when the client doesn't set one.
    expect(array_column($response->json('data.images'), 'sort_order'))->toBe([0, 1]);
});

test('store promotes the first image when the client marks none as primary', function (): void {
    $response = asGalleryAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'No Primary',
        'sku' => 'GAL-2',
        'price' => 25.00,
        'images' => [
            ['image' => 'https://cdn.example.test/first.jpg'],
            ['image' => 'https://cdn.example.test/second.jpg'],
        ],
    ])->assertCreated();

    expect($response->json('data.images.0.is_primary'))->toBeTrue();
    expect($response->json('data.thumbnail'))->toBe('https://cdn.example.test/first.jpg');
});

test('an explicit thumbnail wins over the primary image', function (): void {
    $response = asGalleryAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Explicit Thumb',
        'sku' => 'GAL-3',
        'price' => 25.00,
        'thumbnail' => 'https://cdn.example.test/hero.jpg',
        'images' => [
            ['image' => 'https://cdn.example.test/one.jpg', 'is_primary' => true],
        ],
    ])->assertCreated();

    expect($response->json('data.thumbnail'))->toBe('https://cdn.example.test/hero.jpg');
});

test('update replaces the whole gallery and leaves no orphan rows behind', function (): void {
    $product = galleryProduct();
    ProductImage::create(['product_id' => $product->id, 'image' => 'https://cdn.example.test/old.jpg']);

    $response = asGalleryAdmin()->putJson('/api/v1/admin/products/'.$product->uuid, [
        'images' => [
            ['image' => 'https://cdn.example.test/new.jpg'],
        ],
    ])->assertOk();

    expect(array_column($response->json('data.images'), 'image'))
        ->toBe(['https://cdn.example.test/new.jpg']);

    // Force-deleted, not soft-deleted: a re-save must not accumulate rows.
    expect(ProductImage::withTrashed()->where('product_id', $product->id)->count())->toBe(1);
});

test('omitting the images key leaves the existing gallery untouched', function (): void {
    $product = galleryProduct();
    ProductImage::create(['product_id' => $product->id, 'image' => 'https://cdn.example.test/keep.jpg']);

    $response = asGalleryAdmin()
        ->putJson('/api/v1/admin/products/'.$product->uuid, ['name' => 'Renamed'])
        ->assertOk();

    expect($response->json('data.name'))->toBe('Renamed');
    expect(array_column($response->json('data.images'), 'image'))
        ->toBe(['https://cdn.example.test/keep.jpg']);
});

test('sending an empty images array clears the gallery', function (): void {
    $product = galleryProduct();
    ProductImage::create(['product_id' => $product->id, 'image' => 'https://cdn.example.test/gone.jpg']);

    $response = asGalleryAdmin()
        ->putJson('/api/v1/admin/products/'.$product->uuid, ['images' => []])
        ->assertOk();

    expect($response->json('data.images'))->toBe([]);
});

test('two primary images are rejected rather than resolved by row order', function (): void {
    asGalleryAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Two Primaries',
        'sku' => 'GAL-4',
        'price' => 25.00,
        'images' => [
            ['image' => 'https://cdn.example.test/one.jpg', 'is_primary' => true],
            ['image' => 'https://cdn.example.test/two.jpg', 'is_primary' => true],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('images.1.is_primary');
});

test('an unknown image type is rejected', function (): void {
    asGalleryAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Bad Type',
        'sku' => 'GAL-5',
        'price' => 25.00,
        'images' => [
            ['image' => 'https://cdn.example.test/one.jpg', 'type' => 'hero'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('images.0.type');
});

test('the product list stays light and does not ship galleries', function (): void {
    $product = galleryProduct();
    ProductImage::create(['product_id' => $product->id, 'image' => 'https://cdn.example.test/a.jpg']);

    $response = asGalleryAdmin()->getJson('/api/v1/admin/products')->assertOk();

    expect($response->json('data.items.0'))->not->toHaveKey('images');
});
