<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('the product detail resolves by uuid and returns the product', function (): void {
    $product = Product::factory()->create();

    $this->getJson("/api/v1/products/{$product->uuid}")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.uuid', $product->uuid)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', $product->name);
});

test('product detail exposes the active image gallery in display order', function (): void {
    $product = Product::factory()->create();

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example/gallery-2.jpg',
        'type' => 'gallery',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example/gallery-1.jpg',
        'type' => 'gallery',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'https://cdn.example/hidden.jpg',
        'type' => 'gallery',
        'sort_order' => 0,
        'is_active' => false,
    ]);

    $this->getJson("/api/v1/products/{$product->uuid}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images')
        ->assertJsonPath('data.images.0.url', 'https://cdn.example/gallery-1.jpg')
        ->assertJsonPath('data.images.1.url', 'https://cdn.example/gallery-2.jpg')
        ->assertJsonPath('data.images.0.is_active', true);
});

test('product detail returns only the active variants', function (): void {
    $product = Product::factory()->create();

    ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Active variant',
        'slug' => 'active-variant-'.Str::lower(Str::random(6)),
        'sku' => 'ACTIVE-'.Str::upper(Str::random(6)),
        'price' => 10,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Inactive variant',
        'slug' => 'inactive-variant-'.Str::lower(Str::random(6)),
        'sku' => 'INACTIVE-'.Str::upper(Str::random(6)),
        'price' => 20,
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $this->getJson("/api/v1/products/{$product->uuid}")
        ->assertOk()
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.name', 'Active variant');
});

test('product detail does not leak the internal cost price', function (): void {
    $product = Product::factory()->create(['cost_price' => 99.99]);

    $this->getJson("/api/v1/products/{$product->uuid}")
        ->assertOk()
        ->assertJsonMissingPath('data.cost_price');
});

test('inactive products are not served by the client product detail endpoint', function (): void {
    $product = Product::factory()->inactive()->create();

    $this->getJson("/api/v1/products/{$product->uuid}")
        ->assertNotFound();
});

test('viewing a product detail increments its view counter once', function (): void {
    $product = Product::factory()->create(['views_count' => 0]);

    $this->getJson("/api/v1/products/{$product->uuid}")->assertOk();

    expect($product->fresh()->views_count)->toBe(1);
});

test('the products list endpoint stays light and keeps working', function (): void {
    Product::factory()->count(3)->create();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissingPath('data.0.images');
});