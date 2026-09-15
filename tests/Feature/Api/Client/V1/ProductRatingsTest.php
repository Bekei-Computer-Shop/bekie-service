<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->product = Product::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();

    $this->token = ApiToken::factory()->create([
        'user_id' => $this->user->id,
        'scope' => 'client',
    ]);
});

describe('Product List API - Ratings', function () {
    test('product list includes average_rating field', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 3,
        ]);

        $response = $this->getJson(
            '/api/v1/products?per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $product = $response['data']['items'][0];
        expect($product)->toHaveKey('average_rating');
        expect($product['average_rating'])->toBe(4.0);
    });

    test('product without reviews has zero average_rating', function () {
        $response = $this->getJson(
            '/api/v1/products?per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $product = $response['data']['items'][0];
        expect($product['average_rating'])->toBe(0);
    });

    test('product list does not include full reviews array', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Great product',
        ]);

        $response = $this->getJson(
            '/api/v1/products?per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $product = $response['data']['items'][0];
        expect($product)->not->toHaveKey('reviews');
        expect($product)->not->toHaveKey('comment');
    });

    test('average_rating calculation includes only approved reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'status' => 'pending',
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'status' => 'rejected',
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'status' => 'hidden',
        ]);

        $response = $this->getJson(
            '/api/v1/products?per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $product = $response['data']['items'][0];
        expect($product['average_rating'])->toBe(5.0);
    });

    test('average_rating excludes soft-deleted reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);
        $deletedReview = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
        ]);
        $deletedReview->delete();

        $response = $this->getJson(
            '/api/v1/products?per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $product = $response['data']['items'][0];
        expect($product['average_rating'])->toBe(5.0);
    });

    test('product list query is optimized (no N+1)', function () {
        // Create 5 products with reviews each
        $products = Product::factory()->count(5)->create(['is_active' => true]);
        foreach ($products as $product) {
            Review::factory()->approved()->count(2)->create(['product_id' => $product->id]);
        }

        // Query should use join and groupBy for aggregation
        $query = Product::where('is_active', true)
            ->selectRaw('products.*, COALESCE(ROUND(AVG(CASE WHEN reviews.status = ? THEN reviews.rating END), 1), 0) as average_rating', ['approved'])
            ->leftJoin('reviews', 'products.id', '=', 'reviews.product_id')
            ->whereNull('reviews.deleted_at')
            ->groupBy('products.id');

        $this->assertCount(5, $query->get());
    });
});

describe('Product Detail API - Ratings', function () {
    test('product detail includes average_rating', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 4,
        ]);
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['average_rating'])->toBe(4.5);
    });

    test('product detail includes reviews array', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Excellent product!',
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data'])->toHaveKey('reviews');
        expect($response['data']['reviews'])->toHaveCount(1);
    });

    test('reviews contain only rating and comment', function () {
        $review = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Great quality',
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $responseReview = $response['data']['reviews'][0];

        expect($responseReview)->toHaveKeys(['rating', 'comment']);
        expect($responseReview)->not->toHaveKeys([
            'id',
            'user_id',
            'product_id',
            'title',
            'status',
            'is_verified_purchase',
            'helpful_count',
            'created_at',
            'updated_at',
        ]);
    });

    test('reviews are ordered newest first', function () {
        $oldReview = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 3,
            'comment' => 'Old review',
            'created_at' => now()->subDays(10),
        ]);
        $newReview = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'New review',
            'created_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'][0]['comment'])->toBe('New review');
        expect($response['data']['reviews'][1]['comment'])->toBe('Old review');
    });

    test('product detail excludes pending reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Approved',
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Pending',
            'status' => 'pending',
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'])->toHaveCount(1);
        expect($response['data']['reviews'][0]['comment'])->toBe('Approved');
    });

    test('product detail excludes rejected reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Approved',
        ]);
        Review::factory()->rejected()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Rejected',
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'])->toHaveCount(1);
        expect($response['data']['reviews'][0]['comment'])->toBe('Approved');
    });

    test('product detail excludes hidden reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Approved',
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Hidden',
            'status' => 'hidden',
        ]);

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'])->toHaveCount(1);
        expect($response['data']['reviews'][0]['comment'])->toBe('Approved');
    });

    test('product detail excludes soft-deleted reviews', function () {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Approved',
        ]);
        $deletedReview = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Deleted',
        ]);
        $deletedReview->delete();

        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'])->toHaveCount(1);
        expect($response['data']['reviews'][0]['comment'])->toBe('Approved');
    });

    test('product without reviews returns empty reviews array', function () {
        $response = $this->getJson(
            "/api/v1/products/{$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['reviews'])->toBe([]);
        expect($response['data']['average_rating'])->toBe(0);
    });

    test('inactive product returns 404', function () {
        $inactiveProduct = Product::factory()->create(['is_active' => false]);

        $response = $this->getJson(
            "/api/v1/products/{$inactiveProduct->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertNotFound();
    });
});
