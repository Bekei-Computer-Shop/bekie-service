<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AdminPermissionsSeeder::class);

    $this->admin = User::factory()->superAdmin()->create();

    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $tokens = (new AdminAuthService)->createAdminToken($this->admin);

    $this->headers = [
        'Authorization' => 'Bearer '.$tokens['access_token'],
        'Accept' => 'application/json',
    ];
});

test('admin can list tracked stock products with search and low stock filter', function () {
    $category = Category::factory()->create(['name' => 'Hardware']);

    $healthy = Product::factory()->create([
        'name' => 'NVIDIA RTX 4090',
        'sku' => 'NV-4090-FE',
        'category_id' => $category->id,
        'stock_quantity' => 15,
        'min_stock_alert' => 5,
        'track_inventory' => true,
        'in_stock' => true,
    ]);

    $low = Product::factory()->create([
        'name' => 'AMD Ryzen 9 7950X',
        'sku' => 'AMD-7950X-AM5',
        'category_id' => $category->id,
        'stock_quantity' => 2,
        'min_stock_alert' => 5,
        'track_inventory' => true,
        'in_stock' => true,
    ]);

    $out = Product::factory()->create([
        'name' => 'Samsung 990 Pro 2TB',
        'sku' => 'SAM-990P-2TB',
        'category_id' => $category->id,
        'stock_quantity' => 0,
        'min_stock_alert' => 10,
        'track_inventory' => true,
        'in_stock' => false,
    ]);

    // Untracked product should be excluded
    Product::factory()->create([
        'name' => 'Digital License',
        'sku' => 'DIG-LIC-01',
        'track_inventory' => false,
    ]);

    // 1. List all tracked products
    $response = $this->withHeaders($this->headers)->getJson('/api/v1/admin/stock');
    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.pagination.total', 3);

    // 2. Search by SKU
    $searchResponse = $this->withHeaders($this->headers)->getJson('/api/v1/admin/stock?q=4090');
    $searchResponse->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.sku', 'NV-4090-FE');

    // 3. Filter by low_stock
    $lowResponse = $this->withHeaders($this->headers)->getJson('/api/v1/admin/stock?low_stock=1');
    $lowResponse->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

test('admin can fetch stock alerts', function () {
    Product::factory()->create([
        'name' => 'Low Item',
        'sku' => 'LOW-01',
        'stock_quantity' => 3,
        'min_stock_alert' => 5,
        'track_inventory' => true,
    ]);

    Product::factory()->create([
        'name' => 'Healthy Item',
        'sku' => 'HEALTHY-01',
        'stock_quantity' => 20,
        'min_stock_alert' => 5,
        'track_inventory' => true,
    ]);

    $response = $this->withHeaders($this->headers)->getJson('/api/v1/admin/stock/alerts');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sku', 'LOW-01');
});

test('admin can fetch stock item details and movements by id and uuid', function () {
    $category = Category::factory()->create(['name' => 'Processors']);
    $product = Product::factory()->create([
        'name' => 'AMD Ryzen 9 7950X',
        'sku' => 'AMD-7950X-AM5',
        'category_id' => $category->id,
        'stock_quantity' => 10,
        'min_stock_alert' => 5,
        'track_inventory' => true,
    ]);

    // Perform an initial stock movement
    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'adjust',
        'quantity' => 5,
        'reason' => 'Supplier Delivery',
        'reference' => 'PO-10023',
    ])->assertCreated();

    // Fetch by numeric ID
    $responseById = $this->withHeaders($this->headers)->getJson("/api/v1/admin/stock/{$product->id}");
    $responseById->assertOk()
        ->assertJsonPath('data.name', 'AMD Ryzen 9 7950X')
        ->assertJsonPath('data.stock_quantity', 15)
        ->assertJsonCount(1, 'data.movements')
        ->assertJsonPath('data.movements.0.reason', 'Supplier Delivery');

    // Fetch by UUID
    $responseByUuid = $this->withHeaders($this->headers)->getJson("/api/v1/admin/stock/{$product->uuid}");
    $responseByUuid->assertOk()
        ->assertJsonPath('data.sku', 'AMD-7950X-AM5');
});

test('admin can perform positive and negative stock adjustments and prevent negative stock', function () {
    $product = Product::factory()->create([
        'name' => 'Corsair RAM 32GB',
        'sku' => 'COR-32GB',
        'stock_quantity' => 10,
        'min_stock_alert' => 5,
        'track_inventory' => true,
    ]);

    // 1. Positive adjustment (+5)
    $responsePos = $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'App\\Models\\Product',
        'stockable_id' => $product->id,
        'movement_type' => 'adjust',
        'quantity' => 5,
        'reason' => 'Inventory Recount',
        'reference' => 'Found extra stock on shelf',
    ]);

    $responsePos->assertCreated()
        ->assertJsonPath('data.previous_quantity', 10)
        ->assertJsonPath('data.new_quantity', 15);

    expect($product->fresh()->stock_quantity)->toBe(15);

    // 2. Negative adjustment (-4)
    $responseNeg = $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'adjust',
        'quantity' => -4,
        'reason' => 'Damaged Goods',
        'reference' => 'Water damage in corner',
    ]);

    $responseNeg->assertCreated()
        ->assertJsonPath('data.previous_quantity', 15)
        ->assertJsonPath('data.new_quantity', 11);

    expect($product->fresh()->stock_quantity)->toBe(11);

    // 3. Excessive negative adjustment that would cause negative stock (-20 from 11)
    $responseInvalid = $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'adjust',
        'quantity' => -20,
        'reason' => 'Theft / Loss',
    ]);

    $responseInvalid->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);

    expect($product->fresh()->stock_quantity)->toBe(11);
});

