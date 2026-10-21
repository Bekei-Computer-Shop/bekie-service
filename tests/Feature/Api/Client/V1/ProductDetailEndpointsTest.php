<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('gallery returns active images in order with primary and thumbnail fallbacks', function (): void {
    $product = Product::factory()->create();

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example.test/gallery-later.jpg',
        'type' => 'gallery',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $primary = ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example.test/primary.jpg',
        'type' => 'gallery',
        'sort_order' => 3,
        'is_primary' => true,
        'is_active' => true,
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example.test/thumbnail.jpg',
        'type' => 'thumbnail',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example.test/inactive.jpg',
        'type' => 'gallery',
        'sort_order' => 0,
        'is_active' => false,
    ]);

    $this->getJson("/api/v1/products/{$product->id}/gallery")
        ->assertOk()
        ->assertJsonCount(3, 'data.images')
        ->assertJsonPath('data.images.0.url', 'https://cdn.example.test/thumbnail.jpg')
        ->assertJsonPath('data.images.1.url', 'https://cdn.example.test/gallery-later.jpg')
        ->assertJsonPath('data.primary_image.id', $primary->id)
        ->assertJsonPath('data.thumbnails.0.url', 'https://cdn.example.test/thumbnail.jpg');
});

test('gallery returns an empty list and product thumbnail fallback when no image rows exist', function (): void {
    $product = Product::factory()->create(['thumbnail' => 'https://cdn.example.test/product-thumb.jpg']);

    $this->getJson("/api/v1/products/{$product->id}/gallery")
        ->assertOk()
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.primary_image.url', 'https://cdn.example.test/product-thumb.jpg')
        ->assertJsonPath('data.thumbnails.0.url', 'https://cdn.example.test/product-thumb.jpg');
});

test('related products prioritize category before brand and exclude inactive and current products', function (): void {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $brand = Brand::factory()->create();
    $otherBrand = Brand::factory()->create();

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
    ]);
    $sameCategory = Product::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $otherBrand->id,
        'name' => 'Same category product',
    ]);
    $sameBrand = Product::factory()->create([
        'category_id' => $otherCategory->id,
        'brand_id' => $brand->id,
        'name' => 'Same brand product',
    ]);
    Product::factory()->inactive()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
    ]);
    Product::factory()->create([
        'category_id' => $otherCategory->id,
        'brand_id' => $otherBrand->id,
    ]);

    $this->getJson("/api/v1/products/{$product->id}/related?limit=2")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $sameCategory->id)
        ->assertJsonPath('data.items.1.id', $sameBrand->id)
        ->assertJsonPath('data.meta.limit', 2)
        ->assertJsonMissingPath('data.items.0.cost_price');
});

test('related products validate limits and return an empty list without category or brand matches', function (): void {
    $product = Product::factory()->create(['category_id' => null, 'brand_id' => null]);
    Product::factory()->create();

    $this->getJson("/api/v1/products/{$product->id}/related?limit=25")
        ->assertUnprocessable();

    $this->getJson("/api/v1/products/{$product->id}/related")
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.meta.count', 0);
});

test('specifications group physical properties and dynamic active variant attributes', function (): void {
    $product = Product::factory()->create([
        'weight' => 1.25,
        'length' => 30,
        'width' => null,
        'height' => null,
    ]);
    ProductVariant::create([
        'product_id' => $product->id,
        'name' => '16GB configuration',
        'slug' => 'config-'.Str::lower(Str::random(8)),
        'sku' => 'CONFIG-'.Str::upper(Str::random(8)),
        'attributes' => ['memory' => '16GB', 'chip' => 'M3'],
        'is_active' => true,
        'sort_order' => 0,
    ]);
    ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Inactive configuration',
        'slug' => 'inactive-config-'.Str::lower(Str::random(8)),
        'sku' => 'INACTIVE-'.Str::upper(Str::random(8)),
        'attributes' => ['memory' => '64GB'],
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $this->getJson("/api/v1/products/{$product->id}/specifications")
        ->assertOk()
        ->assertJsonCount(2, 'data.groups')
        ->assertJsonPath('data.groups.0.name', 'Physical properties')
        ->assertJsonPath('data.groups.0.attributes.0.key', 'weight')
        ->assertJsonPath('data.groups.0.attributes.0.value', 1.25)
        ->assertJsonPath('data.groups.1.name', '16GB configuration')
        ->assertJsonPath('data.groups.1.attributes.0.key', 'memory')
        ->assertJsonMissing(['value' => '64GB']);
});

test('specifications return empty groups when no physical or variant attributes exist', function (): void {
    $product = Product::factory()->create([
        'weight' => null,
        'length' => null,
        'width' => null,
        'height' => null,
    ]);

    $this->getJson("/api/v1/products/{$product->id}/specifications")
        ->assertOk()
        ->assertJsonPath('data.product_id', $product->id)
        ->assertJsonPath('data.groups', []);
});

test('warranty policies return the published store policy and do not expose drafts or future pages', function (): void {
    $product = Product::factory()->create();
    $policy = ContentItem::factory()->page()->published()->create([
        'title' => 'Warranty & Returns Policy',
        'slug' => 'warranty-policy',
        'body' => 'Current published warranty and returns terms.',
    ]);
    ContentItem::factory()->page()->create([
        'title' => 'Future Warranty & Returns Policy',
        'slug' => 'future-warranty-policy',
        'status' => 'published',
        'published_at' => now()->addDay(),
    ]);

    $this->getJson("/api/v1/products/{$product->id}/warranty-policies")
        ->assertOk()
        ->assertJsonPath('data.product_id', $product->id)
        ->assertJsonPath('data.policy_source', 'store')
        ->assertJsonPath('data.warranty_and_returns_policy.id', $policy->id)
        ->assertJsonPath('data.warranty_and_returns_policy.body', 'Current published warranty and returns terms.');
});

test('warranty policies return null rather than inventing terms when no published policy exists', function (): void {
    $product = Product::factory()->create();
    ContentItem::factory()->page()->create([
        'title' => 'Warranty & Returns Policy',
        'slug' => 'warranty-policy-draft',
        'status' => 'draft',
    ]);

    $this->getJson("/api/v1/products/{$product->id}/warranty-policies")
        ->assertOk()
        ->assertJsonPath('data.policy_source', null)
        ->assertJsonPath('data.warranty_and_returns_policy', null);
});

test('all four endpoints hide inactive products and return not found for missing products', function (): void {
    $inactive = Product::factory()->inactive()->create();
    $missingId = (string) Str::uuid();
    $paths = [
        'gallery',
        'related',
        'specifications',
        'warranty-policies',
    ];

    foreach ($paths as $path) {
        $this->getJson("/api/v1/products/{$inactive->id}/{$path}")->assertNotFound();
        $this->getJson("/api/v1/products/{$missingId}/{$path}")->assertNotFound();
    }
});

test('specifications eager load variants in one query regardless of variant count', function (): void {
    $product = Product::factory()->create();
    foreach (range(1, 5) as $index) {
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => "Configuration {$index}",
            'slug' => 'config-'.Str::lower(Str::random(8)),
            'sku' => 'CONFIG-'.Str::upper(Str::random(8)),
            'attributes' => ['memory' => "{$index}GB"],
            'is_active' => true,
            'sort_order' => $index,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson("/api/v1/products/{$product->id}/specifications")->assertOk();

    $variantQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'product_variants'));

    DB::disableQueryLog();

    expect($variantQueries)->toHaveCount(1);
});
