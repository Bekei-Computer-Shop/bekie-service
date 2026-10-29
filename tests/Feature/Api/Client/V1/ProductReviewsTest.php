<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Factories\ReviewFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function productReview(Product $product, array $attributes = []): void
{
    ReviewFactory::new()->approved()->create(['product_id' => $product->id, ...$attributes]);
}

test('product reviews list only approved reviews with a public reviewer name', function (): void {
    $product = Product::factory()->create();
    $reviewer = User::factory()->create(['first_name' => 'Sokha', 'last_name' => 'dara', 'email' => 'sokha@example.test']);

    productReview($product, [
        'user_id' => $reviewer->id,
        'rating' => 5,
        'title' => 'Fast and quiet',
        'comment' => 'Runs every game I own.',
        'is_verified_purchase' => true,
    ]);
    ReviewFactory::new()->create(['product_id' => $product->id, 'status' => 'pending']);
    ReviewFactory::new()->rejected()->create(['product_id' => $product->id]);
    productReview(Product::factory()->create());

    $response = $this->getJson("/api/v1/products/{$product->id}/reviews")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.product_id', $product->id)
        ->assertJsonPath('data.items.0.rating', 5)
        ->assertJsonPath('data.items.0.title', 'Fast and quiet')
        ->assertJsonPath('data.items.0.comment', 'Runs every game I own.')
        ->assertJsonPath('data.items.0.is_verified_purchase', true)
        ->assertJsonPath('data.items.0.customer.name', 'Sokha D.')
        ->assertJsonPath('data.pagination.total', 1);

    expect($response->content())->not->toContain('sokha@example.test')
        ->and($response->json('data.items.0'))->not->toHaveKeys(['user_id', 'order_id', 'status']);
});

test('the review summary averages approved reviews and breaks them down by star', function (): void {
    $product = Product::factory()->create();
    foreach ([5, 5, 4, 2] as $rating) {
        productReview($product, ['rating' => $rating]);
    }
    ReviewFactory::new()->create(['product_id' => $product->id, 'rating' => 1, 'status' => 'pending']);

    $summary = $this->getJson("/api/v1/products/{$product->id}/reviews")
        ->assertOk()
        ->json('data.summary');

    expect($summary['average_rating'])->toEqual(4.0)
        ->and($summary['total_reviews'])->toBe(4)
        ->and($summary['rating_breakdown'])->toBe([
            ['rating' => 5, 'count' => 2, 'percentage' => 50],
            ['rating' => 4, 'count' => 1, 'percentage' => 25],
            ['rating' => 3, 'count' => 0, 'percentage' => 0],
            ['rating' => 2, 'count' => 1, 'percentage' => 25],
            ['rating' => 1, 'count' => 0, 'percentage' => 0],
        ]);
});

test('a product without reviews returns an empty list and a zero summary', function (): void {
    $product = Product::factory()->create();

    $this->getJson("/api/v1/products/{$product->id}/reviews")
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.summary.total_reviews', 0)
        ->assertJsonPath('data.summary.average_rating', 0)
        ->assertJsonPath('data.pagination.last_page', 1);
});

test('reviews can be filtered by stars and verified purchase while the summary stays whole', function (): void {
    $product = Product::factory()->create();
    productReview($product, ['rating' => 5, 'is_verified_purchase' => true]);
    productReview($product, ['rating' => 5, 'is_verified_purchase' => false]);
    productReview($product, ['rating' => 3, 'is_verified_purchase' => true]);

    $this->getJson("/api/v1/products/{$product->id}/reviews?rating=5")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.summary.total_reviews', 3);

    $this->getJson("/api/v1/products/{$product->id}/reviews?rating=5&verified_only=1")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.is_verified_purchase', true);
});

test('reviews are sorted and paginated', function (): void {
    $product = Product::factory()->create();
    productReview($product, ['rating' => 3, 'helpful_count' => 9, 'created_at' => now()->subDays(3)]);
    productReview($product, ['rating' => 5, 'helpful_count' => 1, 'created_at' => now()->subDays(2)]);
    productReview($product, ['rating' => 1, 'helpful_count' => 4, 'created_at' => now()->subDay()]);

    $ratings = fn (string $query): array => array_column(
        $this->getJson("/api/v1/products/{$product->id}/reviews?{$query}")->assertOk()->json('data.items'),
        'rating',
    );

    expect($ratings('sort=newest'))->toBe([1, 5, 3])
        ->and($ratings('sort=oldest'))->toBe([3, 5, 1])
        ->and($ratings('sort=highest'))->toBe([5, 3, 1])
        ->and($ratings('sort=lowest'))->toBe([1, 3, 5])
        ->and($ratings('sort=most_helpful'))->toBe([3, 1, 5]);

    $this->getJson("/api/v1/products/{$product->id}/reviews?per_page=2&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.pagination.total', 3)
        ->assertJsonPath('data.pagination.last_page', 2)
        ->assertJsonPath('data.pagination.current_page', 2);
});

test('review list parameters are validated', function (): void {
    $product = Product::factory()->create();

    $this->getJson("/api/v1/products/{$product->id}/reviews?rating=6&sort=random&per_page=51")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating', 'sort', 'per_page']);
});

test('reviews of inactive or missing products are not found', function (): void {
    $inactive = Product::factory()->inactive()->create();
    productReview($inactive);

    $this->getJson("/api/v1/products/{$inactive->id}/reviews")->assertNotFound();
    $this->getJson('/api/v1/products/'.Str::uuid().'/reviews')->assertNotFound();
});

test('related products carry their approved rating and stock for the product card', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $related = Product::factory()->create(['category_id' => $category->id, 'in_stock' => true]);
    productReview($related, ['rating' => 5]);
    productReview($related, ['rating' => 4]);
    ReviewFactory::new()->create(['product_id' => $related->id, 'rating' => 1, 'status' => 'pending']);

    $this->getJson("/api/v1/products/{$product->id}/related")
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $related->id)
        ->assertJsonPath('data.items.0.average_rating', 4.5)
        ->assertJsonPath('data.items.0.reviews_count', 2)
        ->assertJsonPath('data.items.0.in_stock', true);
});
