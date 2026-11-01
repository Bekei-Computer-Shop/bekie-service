<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Database\Factories\ReviewFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_products_success(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'sku',
                        'price',
                        'sale_price',
                        'category_id',
                        'brand_id',
                    ],
                ],
            ]);
    }

    public function test_list_products_excludes_inactive(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_products_by_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products?category_id={$category->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_products_by_brand(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create([
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);
        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products?brand_id={$brand->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_products_pagination(): void
    {
        Product::factory()->count(25)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?page=2');

        $response->assertOk();
    }

    public function test_product_lists_include_brand(): void
    {
        $this->travelTo(now());
        $brand = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple', 'logo' => 'apple.png']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'is_active' => true,
            'price' => 100,
            'sale_price' => 80,
            'in_stock' => true,
            'stock_quantity' => 5,
            'created_at' => now()->subDay(),
        ]);
        $this->recordPaidSale($product, 3);
        ReviewFactory::new()->create(['product_id' => $product->id, 'rating' => 5, 'status' => 'approved']);

        $expected = ['id' => $brand->id, 'name' => 'Apple', 'slug' => 'apple', 'logo' => 'apple.png'];

        foreach (['/api/v1/products', '/api/v1/products/best-selling', '/api/v1/products/on-promotion', '/api/v1/products/new'] as $uri) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonPath('data.0.brand_id', $brand->id)
                ->assertJsonPath('data.0.brand', $expected);
        }
    }

    public function test_product_list_brand_is_null_without_a_brand(): void
    {
        Product::factory()->create(['brand_id' => null, 'is_active' => true]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.brand_id', null)
            ->assertJsonPath('data.0.brand', null);
    }

    public function test_product_list_eager_loads_brand(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $brandQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "brands"'));

        $this->assertCount(1, $brandQueries);
    }

    public function test_best_selling_products_use_paid_sales_and_approved_reviews(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'sales_count' => 0]);
        $pendingProduct = Product::factory()->create(['is_active' => true]);
        $unratedProduct = Product::factory()->create(['is_active' => true]);
        $this->recordPaidSale($product, 5);
        $this->recordPaidSale($pendingProduct, 5, 'pending');
        $this->recordPaidSale($unratedProduct, 10);
        ReviewFactory::new()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'status' => 'approved',
        ]);
        ReviewFactory::new()->create([
            'product_id' => $product->id,
            'rating' => 1,
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/products/best-selling')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.sales_count', 5)
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.reviews_count', 1);
    }

    public function test_on_promotion_products_require_a_lower_sale_price(): void
    {
        $promoted = Product::factory()->create([
            'is_active' => true,
            'price' => 100,
            'sale_price' => 80,
        ]);
        Product::factory()->create([
            'is_active' => true,
            'price' => 100,
            'sale_price' => 100,
        ]);

        $this->getJson('/api/v1/products/on-promotion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $promoted->id)
            ->assertJsonPath('data.0.price', 100)
            ->assertJsonPath('data.0.sale_price', 80);
    }

    public function test_on_promotion_products_include_live_targeted_promotions(): void
    {
        $parent = Category::factory()->create(['is_active' => true]);
        $child = Category::factory()->create(['is_active' => true, 'parent_id' => $parent->id]);
        $direct = Product::factory()->create(['is_active' => true, 'price' => 200, 'sale_price' => null]);
        $inSubcategory = Product::factory()->create(['is_active' => true, 'price' => 50, 'sale_price' => null, 'category_id' => $child->id]);
        $groupOnly = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null]);
        $expiredOnly = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null]);

        $this->promotion(['target_type' => Coupon::TARGET_PRODUCTS, 'value' => 10])->products()->attach($direct->id);
        $this->promotion(['target_type' => Coupon::TARGET_CATEGORIES, 'type' => 'fixed', 'value' => 5, 'include_subcategories' => true])
            ->categories()->attach($parent->id);
        $grouped = $this->promotion(['target_type' => Coupon::TARGET_PRODUCTS]);
        $grouped->products()->attach($groupOnly->id);
        $grouped->customerGroups()->attach(CustomerGroup::create(['name' => 'Members', 'slug' => 'members', 'is_active' => true])->id);
        $this->promotion(['target_type' => Coupon::TARGET_PRODUCTS, 'expires_at' => now()->subDay()])->products()->attach($expiredOnly->id);

        $data = collect($this->getJson('/api/v1/products/on-promotion')->assertOk()->json('data'))->keyBy('id');

        expect($data->keys()->sort()->values()->all())->toBe(collect([$direct->id, $inSubcategory->id])->sort()->values()->all())
            ->and($data[$direct->id]['promotion']['discount_percentage'])->toEqual(10)
            ->and($data[$direct->id]['promotion']['discount_amount'])->toBeNull()
            ->and($data[$direct->id]['promotion']['final_price'])->toEqual(180)
            ->and($data[$inSubcategory->id]['promotion']['type'])->toBe('fixed')
            ->and($data[$inSubcategory->id]['promotion']['discount_amount'])->toEqual(5)
            ->and($data[$inSubcategory->id]['promotion']['discount_percentage'])->toBeNull()
            ->and($data[$inSubcategory->id]['promotion']['final_price'])->toEqual(45);
    }

    public function test_product_list_and_detail_show_the_live_promotion(): void
    {
        $promoted = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => 80]);
        $viaVariant = Product::factory()->create(['is_active' => true, 'price' => 40, 'sale_price' => null]);
        $plain = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null]);
        $variant = ProductVariant::create([
            'product_id' => $viaVariant->id, 'name' => 'Blue', 'slug' => 'blue', 'sku' => 'PROMO-BLUE',
            'price' => 40, 'in_stock' => true, 'stock_quantity' => 3, 'is_active' => true,
        ]);
        $promotion = $this->promotion(['name' => 'Ten Off', 'kind' => 'flash_sale', 'target_type' => Coupon::TARGET_PRODUCTS, 'value' => 10]);
        $promotion->products()->attach($promoted->id);
        $promotion->variants()->attach($variant->id);

        $list = collect($this->getJson('/api/v1/products')->assertOk()->json('data'))->keyBy('id');

        expect($list[$promoted->id]['promotion'])->toMatchArray([
            'name' => 'Ten Off', 'kind' => 'flash_sale', 'discount_amount' => null, 'discount_percentage' => 10, 'final_price' => 72,
        ])
            ->and($list[$viaVariant->id]['promotion']['final_price'])->toEqual(36)
            ->and($list[$plain->id]['promotion'])->toBeNull();

        $this->getJson("/api/v1/products/{$promoted->id}")
            ->assertOk()
            ->assertJsonPath('data.promotion.name', 'Ten Off')
            ->assertJsonPath('data.promotion.kind', 'flash_sale')
            ->assertJsonPath('data.promotion.final_price', 72);
    }

    public function test_fixed_promotion_shows_only_the_discount_amount(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 599, 'sale_price' => null]);
        $promotion = $this->promotion(['name' => 'Flat Discount', 'type' => 'fixed', 'target_type' => Coupon::TARGET_PRODUCTS, 'value' => 15]);
        $promotion->products()->attach($product->id);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.promotion.discount_amount', 15)
            ->assertJsonPath('data.promotion.discount_percentage', null)
            ->assertJsonPath('data.promotion.final_price', 584);
    }

    public function test_capped_percentage_promotion_shows_only_the_effective_percentage(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'price' => 599, 'sale_price' => null]);
        $promotion = $this->promotion(['type' => 'percentage', 'value' => 18, 'max_discount_amount' => 50, 'target_type' => Coupon::TARGET_PRODUCTS]);
        $promotion->products()->attach($product->id);

        // 18% of 599 is 107.82, capped at 50: 50 / 599 is 8.3%, rounded down.
        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.promotion.discount_percentage', 8.3)
            ->assertJsonPath('data.promotion.discount_amount', null)
            ->assertJsonPath('data.promotion.final_price', 549);
    }

    public function test_products_list_the_live_site_wide_codes_without_a_per_unit_price(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $plain = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null, 'category_id' => $category->id]);
        $targeted = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null, 'category_id' => $category->id]);
        $this->promotion(['name' => 'Product Deal', 'target_type' => Coupon::TARGET_PRODUCTS])->products()->attach($targeted->id);

        $welcome = $this->promotion([
            'name' => 'Welcome', 'code' => 'WELCOME10', 'value' => 10, 'min_order_amount' => 50,
            'max_discount_amount' => 20, 'expires_at' => now()->addDays(10),
        ]);
        $flat = $this->promotion(['name' => 'Flat', 'code' => 'FLAT15', 'type' => 'fixed', 'value' => 15, 'starts_at' => now()->subDays(30)]);
        // Never listed: expired, switched off, out of uses, limited to a customer group.
        $this->promotion(['code' => 'EXPIRED', 'expires_at' => now()->subDay()]);
        $this->promotion(['code' => 'OFF', 'is_active' => false]);
        $this->promotion(['code' => 'USEDUP', 'usage_limit' => 1, 'used_count' => 1]);
        $this->promotion(['code' => 'MEMBERS'])->customerGroups()
            ->attach(CustomerGroup::create(['name' => 'Members', 'slug' => 'members', 'is_active' => true])->id);

        $expected = [
            [
                'id' => $welcome->id, 'name' => 'Welcome', 'code' => 'WELCOME10', 'type' => 'percentage', 'kind' => null,
                'value' => 10, 'min_order_amount' => 50, 'max_discount_amount' => 20,
                'expires_at' => $welcome->fresh()->expires_at->toIso8601String(),
            ],
            [
                'id' => $flat->id, 'name' => 'Flat', 'code' => 'FLAT15', 'type' => 'fixed', 'kind' => null,
                'value' => 15, 'min_order_amount' => null, 'max_discount_amount' => null, 'expires_at' => null,
            ],
        ];

        // The newest campaign first, like GET /promotions. The code is taken off the
        // whole order, so there is no per-unit discount or final price here.
        $list = collect($this->getJson('/api/v1/products')->assertOk()->json('data'))->keyBy('id');
        expect($list[$plain->id]['site_promotions'])->toEqual($expected)
            ->and($list[$targeted->id]['site_promotions'])->toEqual($expected)
            ->and($list[$plain->id]['promotion'])->toBeNull()
            ->and($list[$targeted->id]['promotion']['name'])->toBe('Product Deal');

        $this->getJson("/api/v1/products/{$plain->id}")->assertOk()->assertJsonPath('data.site_promotions', $expected);
        $this->getJson("/api/v1/products/{$plain->id}/related")->assertOk()->assertJsonPath('data.items.0.site_promotions', $expected);
    }

    public function test_site_wide_codes_do_not_make_a_product_count_as_on_promotion(): void
    {
        Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null]);
        $this->promotion(['code' => 'WELCOME10']);

        $this->getJson('/api/v1/products/on-promotion')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.site_promotions.0.code', 'WELCOME10');
    }

    /** @param array<string, mixed> $attributes */
    private function promotion(array $attributes): Coupon
    {
        return Coupon::create(array_merge([
            'name' => 'Promo',
            'code' => uniqid('PROMO-'),
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now()->subDay(),
            'is_active' => true,
        ], $attributes));
    }

    private function recordPaidSale(Product $product, int $quantity, string $paymentStatus = 'paid'): void
    {
        $order = Order::create([
            'order_number' => uniqid('ORD-', true),
            'subtotal' => $quantity * 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => $quantity * 100,
            'currency' => 'USD',
            'payment_method' => 'manual',
            'payment_status' => $paymentStatus,
            'status' => 'processing',
            'shipping_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 100,
            'sale_price' => null,
            'cost_price' => null,
            'subtotal' => $quantity * 100,
            'discount' => 0,
            'tax' => 0,
            'total' => $quantity * 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity_shipped' => 0,
            'quantity_refunded' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_get_product_detail(): void
    {
        $product = Product::factory()->create([
            'name' => 'Laptop Pro',
            'slug' => 'laptop-pro',
            'sku' => 'LAPTOP-001',
            'price' => 1299.99,
            'sale_price' => 999.99,
            'short_description' => 'High performance laptop',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Laptop Pro')
            ->assertJsonPath('data.slug', 'laptop-pro')
            ->assertJsonPath('data.price', 1299.99)
            ->assertJsonPath('data.sale_price', 999.99)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'price',
                    'sale_price',
                    'short_description',
                    'description',
                    'category_id',
                    'brand_id',
                    'images',
                    'variants',
                ],
            ]);
    }

    public function test_public_product_responses_hide_internal_inventory_fields(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 25,
            'min_stock_alert' => 5,
            'track_inventory' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Inventory test variant',
            'slug' => 'inventory-test-variant',
            'sku' => 'INVENTORY-TEST-VARIANT',
            'price' => 10,
            'in_stock' => true,
            'stock_quantity' => 10,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $listResponse = $this->getJson('/api/v1/products');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.in_stock', 1)
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.min_stock_alert')
            ->assertJsonMissingPath('data.0.track_inventory');

        $detailResponse = $this->getJson("/api/v1/products/{$product->id}");
        $detailResponse->assertOk()
            ->assertJsonPath('data.in_stock', 1)
            ->assertJsonMissingPath('data.stock_quantity')
            ->assertJsonMissingPath('data.min_stock_alert')
            ->assertJsonMissingPath('data.track_inventory');

        $variantResponse = $this->getJson("/api/v1/products/{$product->id}/variants");
        $variantResponse->assertOk()
            ->assertJsonPath('data.0.in_stock', 1)
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.track_inventory');
    }

    public function test_get_product_by_uuid(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_get_product_includes_images(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductImage::create([
            'product_id' => $product->id,
            'image' => 'https://cdn.example.com/product.jpg',
            'type' => 'gallery',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.images.0.url', 'https://cdn.example.com/product.jpg')
            ->assertJsonPath('data.images.0.is_active', true);
    }

    public function test_get_product_excludes_cost_price(): void
    {
        $product = Product::factory()->create([
            'cost_price' => 500.00,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonMissingPath('data.cost_price');
    }

    public function test_get_inactive_product_returns_not_found(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertNotFound();
    }

    public function test_get_nonexistent_product_returns_not_found(): void
    {
        $response = $this->getJson('/api/v1/products/9999');

        $response->assertNotFound();
    }

    public function test_product_detail_increments_view_count(): void
    {
        $product = Product::factory()->create(['views_count' => 0, 'is_active' => true]);

        $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        expect($product->fresh()->views_count)->toBe(1);
    }
}
