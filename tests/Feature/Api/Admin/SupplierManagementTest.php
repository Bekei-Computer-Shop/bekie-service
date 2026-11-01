<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function supplierTestHeaders(User $user): array
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));
    $tokens = (new AdminAuthService)->createAdminToken($user);

    return [
        'Authorization' => 'Bearer '.$tokens['access_token'],
        'Accept' => 'application/json',
    ];
}

beforeEach(function () {
    $this->seed(AdminPermissionsSeeder::class);

    $this->admin = User::factory()->superAdmin()->create();
    $this->headers = supplierTestHeaders($this->admin);
});

test('admin can create, list, update and delete suppliers', function () {
    $created = $this->withHeaders($this->headers)->postJson('/api/v1/admin/suppliers', [
        'name' => 'Phnom Penh Components',
        'code' => 'PPC-01',
        'phone' => '+855 12 000 000',
        'email' => 'sales@ppc.test',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.name', 'Phnom Penh Components')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_default', false);

    $id = $created->json('data.id');

    $this->withHeaders($this->headers)->getJson('/api/v1/admin/suppliers?q=PPC')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.code', 'PPC-01');

    $this->withHeaders($this->headers)->patchJson("/api/v1/admin/suppliers/{$id}", ['contact_person' => 'Dara'])
        ->assertOk()
        ->assertJsonPath('data.contact_person', 'Dara');

    // Codes are unique among live suppliers.
    $this->withHeaders($this->headers)->postJson('/api/v1/admin/suppliers', ['name' => 'Copy', 'code' => 'PPC-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->withHeaders($this->headers)->deleteJson("/api/v1/admin/suppliers/{$id}")->assertNoContent();
    expect(Supplier::withTrashed()->find($id)->trashed())->toBeTrue();

    // ...so a deleted supplier's code can be used again.
    $this->withHeaders($this->headers)->postJson('/api/v1/admin/suppliers', ['name' => 'Reborn', 'code' => 'PPC-01'])
        ->assertCreated();
});

test('only one active supplier is the store default', function () {
    $first = Supplier::factory()->create(['is_default' => true]);

    $second = $this->withHeaders($this->headers)->postJson('/api/v1/admin/suppliers', [
        'name' => 'New Default',
        'is_default' => true,
    ])->assertCreated();

    expect($first->fresh()->is_default)->toBeFalse();
    expect(Supplier::where('is_default', true)->pluck('id')->all())->toBe([$second->json('data.id')]);

    // Deactivating the default supplier also drops its default flag.
    $this->withHeaders($this->headers)->patchJson('/api/v1/admin/suppliers/'.$second->json('data.id'), ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_default', false);
});

test('a supplier still named as a product default cannot be deleted', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->create(['default_supplier_id' => $supplier->id]);

    $this->withHeaders($this->headers)->deleteJson("/api/v1/admin/suppliers/{$supplier->id}")
        ->assertStatus(422);

    expect($supplier->fresh()->trashed())->toBeFalse();
});

test('stock in records the supplier and unit cost, and history keeps a deleted supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Siem Reap Wholesale']);
    $product = Product::factory()->create(['stock_quantity' => 2, 'track_inventory' => true]);

    $response = $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'stock_in',
        'quantity' => 5,
        'supplier_id' => $supplier->id,
        'unit_cost' => 12.5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.supplier.name', 'Siem Reap Wholesale')
        ->assertJsonPath('data.unit_cost', 12.5)
        ->assertJsonPath('data.new_quantity', 7);

    $supplier->delete();

    $this->withHeaders($this->headers)->getJson('/api/v1/admin/stock/movements?supplier_id='.$supplier->id)
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.supplier.name', 'Siem Reap Wholesale');
});

test('stock in without a supplier stays without one even when the product has a default', function () {
    // A stock_in is also how customer returns and recounts arrive, so the
    // product's default supplier is a form suggestion, never filled in here.
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 0, 'track_inventory' => true, 'default_supplier_id' => $supplier->id]);

    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'stock_in',
        'quantity' => 1,
        'reason' => 'Customer Return',
    ])->assertCreated()->assertJsonPath('data.supplier', null);
});

test('a supplier is rejected on recounts and when inactive', function () {
    $active = Supplier::factory()->create();
    $inactive = Supplier::factory()->inactive()->create();
    $product = Product::factory()->create(['stock_quantity' => 10, 'track_inventory' => true]);

    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'reconcile',
        'quantity' => 8,
        'supplier_id' => $active->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);

    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements', [
        'stockable_type' => 'product',
        'stockable_id' => $product->id,
        'movement_type' => 'stock_in',
        'quantity' => 1,
        'supplier_id' => $inactive->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);

    expect($product->fresh()->stock_quantity)->toBe(10);
});

test('bulk movements apply the delivery supplier to rows that name none', function () {
    $delivery = Supplier::factory()->create();
    $other = Supplier::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 0, 'track_inventory' => true]);
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0]);
    $large = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0]);

    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements/bulk', [
        'supplier_id' => $delivery->id,
        'items' => [
            ['stockable_type' => 'variant', 'stockable_id' => $small->id, 'movement_type' => 'stock_in', 'quantity' => 3, 'unit_cost' => 4],
            ['stockable_type' => 'variant', 'stockable_id' => $large->id, 'movement_type' => 'stock_in', 'quantity' => 2, 'supplier_id' => $other->id],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.0.supplier.id', $delivery->id)
        ->assertJsonPath('data.0.unit_cost', 4)
        ->assertJsonPath('data.1.supplier.id', $other->id);

    // A row-level supplier on a movement type that can't carry one is refused.
    $this->withHeaders($this->headers)->postJson('/api/v1/admin/stock/movements/bulk', [
        'items' => [
            ['stockable_type' => 'variant', 'stockable_id' => $small->id, 'movement_type' => 'reconcile', 'quantity' => 1, 'supplier_id' => $delivery->id],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.supplier_id']);

    expect(StockMovement::count())->toBe(2);
});

test('products save and return a default supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Battambang Traders']);
    $category = Category::factory()->create();

    $created = $this->withHeaders($this->headers)->postJson('/api/v1/admin/products', [
        'category_id' => $category->id,
        'name' => 'Supplier Test Product',
        'sku' => 'SUP-TEST-1',
        'price' => 10,
        'default_supplier_id' => $supplier->id,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.default_supplier_id', $supplier->id)
        ->assertJsonPath('data.default_supplier.name', 'Battambang Traders');

    $productId = $created->json('data.id');

    $this->withHeaders($this->headers)->getJson("/api/v1/admin/stock/{$productId}")
        ->assertOk()
        ->assertJsonPath('data.default_supplier.id', $supplier->id);

    $this->withHeaders($this->headers)->putJson("/api/v1/admin/products/{$productId}", ['default_supplier_id' => null])
        ->assertOk()
        ->assertJsonPath('data.default_supplier_id', null);
});

test('roles without supplier or stock access cannot list suppliers', function () {
    $staff = User::factory()->create(['is_admin' => true, 'role' => 'staff']);
    $staff->assignRole('staff');

    $this->withHeaders(supplierTestHeaders($staff))->getJson('/api/v1/admin/suppliers')
        ->assertForbidden();
});
