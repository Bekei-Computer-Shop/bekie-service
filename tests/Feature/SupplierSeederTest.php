<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The seeder's own codes: the factory's random `SUP-XXXXXX` codes never match. */
function seededSupplierCodes(): array
{
    return array_column(SupplierSeeder::SUPPLIERS, 'code');
}

function openingReceipt(ProductVariant $variant, int $quantity): StockMovement
{
    return StockMovement::query()->create([
        'stockable_type' => $variant->getMorphClass(),
        'stockable_id' => (string) $variant->id,
        'movement_type' => 'stock_in',
        'quantity' => $quantity,
        'previous_quantity' => 0,
        'new_quantity' => $quantity,
        'reason' => 'Opening stock received',
        'reference' => DemoData::STOCK_MOVEMENT_REFERENCE,
    ]);
}

test('seeds suppliers, links products by brand and names the supplier on opening stock', function () {
    $logitech = Brand::factory()->create(['name' => 'Logitech']);
    $noSupplierBrand = Brand::factory()->create(['name' => 'Obscure Brand']);

    $mouse = Product::factory()->create(['brand_id' => $logitech->id, 'cost_price' => 31.50]);
    $mouseVariant = ProductVariant::factory()->create(['product_id' => $mouse->id, 'cost_price' => 29.75]);
    $mouseReceipt = openingReceipt($mouseVariant, 12);

    $gadget = Product::factory()->create(['brand_id' => $noSupplierBrand->id, 'cost_price' => 8.00]);
    $gadgetVariant = ProductVariant::factory()->create(['product_id' => $gadget->id, 'cost_price' => null]);
    $gadgetReceipt = openingReceipt($gadgetVariant, 5);

    // An admin's own choice is never overwritten.
    $chosen = Supplier::factory()->create();
    $keeper = Product::factory()->create(['brand_id' => $logitech->id, 'default_supplier_id' => $chosen->id]);

    $this->seed(SupplierSeeder::class);

    $gaming = Supplier::where('code', 'SUP-003')->firstOrFail();
    $storeDefault = Supplier::where('is_default', true)->sole();

    expect(Supplier::whereIn('code', seededSupplierCodes())->count())->toBe(count(SupplierSeeder::SUPPLIERS))
        ->and($storeDefault->code)->toBe('SUP-001')
        ->and(Supplier::where('code', 'SUP-011')->value('is_active'))->toBeFalse()
        ->and($mouse->fresh()->default_supplier_id)->toBe($gaming->id)
        ->and($gadget->fresh()->default_supplier_id)->toBeNull()
        ->and($keeper->fresh()->default_supplier_id)->toBe($chosen->id);

    // Variant cost wins; with none, the product's; no brand match, the store default.
    expect($mouseReceipt->fresh())
        ->supplier_id->toBe($gaming->id)
        ->unit_cost->toBe('29.75');
    expect($gadgetReceipt->fresh())
        ->supplier_id->toBe($storeDefault->id)
        ->unit_cost->toBe('8.00');
});

test('re-running updates the suppliers instead of duplicating them', function () {
    $this->seed(SupplierSeeder::class);
    Supplier::where('code', 'SUP-002')->firstOrFail()->delete();
    Supplier::factory()->create(['is_default' => true]);

    $this->seed(SupplierSeeder::class);

    expect(Supplier::withTrashed()->whereIn('code', seededSupplierCodes())->count())->toBe(count(SupplierSeeder::SUPPLIERS))
        ->and(Supplier::where('code', 'SUP-002')->exists())->toBeTrue()
        ->and(Supplier::where('is_default', true)->pluck('code')->all())->toBe(['SUP-001']);
});
