<?php

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock movements update inventory and create audit history', function () {
    $product = Product::factory()->create([
        'stock_quantity' => 10,
        'min_stock_alert' => 3,
        'track_inventory' => true,
        'in_stock' => true,
    ]);

    $service = app(StockService::class);

    $service->adjust($product, 5, 'manual', 'Added stock for a promotion');
    expect($product->fresh()->stock_quantity)->toBe(15);

    $service->reconcile($product, 7, 'cycle-count', 'Reconciled to physical count');
    expect($product->fresh()->stock_quantity)->toBe(7);

    $service->stockIn($product, 3, 'purchase-order', 'Supplier receipt');
    expect($product->fresh()->stock_quantity)->toBe(10);

    $service->stockOut($product, 2, 'sales', 'Order shipment');
    expect($product->fresh()->stock_quantity)->toBe(8);

    $service->transfer($product, 3, 'warehouse-a', 'warehouse-b', 'Stock transfer between locations');
    expect($product->fresh()->stock_quantity)->toBe(5);

    $history = $product->stockMovements()->latest()->get();
    expect($history)->toHaveCount(5);
    expect($history->first()->movement_type)->toBe('adjust');
    expect($history->last()->movement_type)->toBe('transfer');
});
