<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create();
    $this->order = Order::factory()->create(['user_id' => $this->user->id]);

    $this->token = ApiToken::factory()->create([
        'user_id' => $this->user->id,
        'scope' => 'client',
    ]);
});

describe('Create Review', function () {
    test('customer can create a review', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'rating' => 5,
                'title' => 'Excellent product',
                'comment' => 'Very happy with this purchase!',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.rating', 5);
        $response->assertJsonPath('data.title', 'Excellent product');

        expect(Review::count())->toBe(1);
        expect(Review::first()->user_id)->toBe($this->user->id);
    });

    test('customer can create review with verified purchase order', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'order_id' => $this->order->id,
                'rating' => 4,
                'comment' => 'Good quality',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertCreated();
        expect(Review::first()->order_id)->toBe($this->order->id);
    });

    test('review is created with pending status', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'rating' => 3,
                'comment' => 'Average product',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertCreated();
        expect(Review::first()->status)->toBe('pending');
    });

    test('customer cannot submit duplicate review for same product', function () {
        Review::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'rating' => 5,
                'comment' => 'Another review',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('product_id');
    });

    test('rating is required and must be between 1-5', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'comment' => 'Missing rating',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rating');
    });

    test('rating must be between 1 and 5', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'rating' => 10,
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rating');
    });

    test('product_id must be valid UUID and exist', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => 'invalid-uuid',
                'rating' => 4,
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('product_id');
    });

    test('comment must not exceed 2000 characters', function () {
        $response = $this->postJson(
            '/api/v1/reviews',
            [
                'product_id' => $this->product->id,
                'rating' => 4,
                'comment' => str_repeat('a', 2001),
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('comment');
    });

    test('unauthenticated user cannot create review', function () {
        $response = $this->postJson('/api/v1/reviews', [
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);

        $response->assertUnauthorized();
    });
});

describe('Get Customer Reviews', function () {
    test('customer can retrieve their reviews', function () {
        Review::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(
            '/api/v1/reviews',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(5);
        expect($response['data']['pagination']['total'])->toBe(5);
    });

    test('customer can see paginated reviews', function () {
        Review::factory()->count(25)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(
            '/api/v1/reviews?page=2&per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(10);
        expect($response['data']['pagination']['current_page'])->toBe(2);
        expect($response['data']['pagination']['total'])->toBe(25);
    });

    test('customer can sort reviews by rating', function () {
        Review::factory()->create(['user_id' => $this->user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $this->user->id, 'rating' => 1]);
        Review::factory()->create(['user_id' => $this->user->id, 'rating' => 3]);

        $response = $this->getJson(
            '/api/v1/reviews?sort=rating&direction=desc',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'][0]['rating'])->toBe(5);
        expect($response['data']['items'][1]['rating'])->toBe(3);
        expect($response['data']['items'][2]['rating'])->toBe(1);
    });

    test('customer only sees their own reviews', function () {
        $otherUser = User::factory()->create();
        Review::factory()->create(['user_id' => $this->user->id]);
        Review::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson(
            '/api/v1/reviews',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        expect($response['data']['items'])->toHaveCount(1);
    });
});

describe('Get Single Review', function () {
    test('customer can view their review', function () {
        $review = Review::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(
            "/api/v1/reviews/{$review->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $review->id);
    });

    test('customer cannot view another customer\'s review', function () {
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson(
            "/api/v1/reviews/{$review->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertForbidden();
    });
});

describe('Update Review', function () {
    test('customer can update their review', function () {
        $review = Review::factory()->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(
            "/api/v1/reviews/{$review->id}",
            [
                'rating' => 4,
                'comment' => 'Updated comment',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['rating'])->toBe(4);
        expect($response['data']['comment'])->toBe('Updated comment');

        $review->refresh();
        expect($review->rating)->toBe(4);
    });

    test('customer cannot update another customer\'s review', function () {
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->patchJson(
            "/api/v1/reviews/{$review->id}",
            ['rating' => 4],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertForbidden();
    });

    test('customer can update partial review fields', function () {
        $review = Review::factory()->create([
            'user_id' => $this->user->id,
            'rating' => 3,
            'comment' => 'Original comment',
        ]);

        $response = $this->patchJson(
            "/api/v1/reviews/{$review->id}",
            ['rating' => 5],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $review->refresh();
        expect($review->rating)->toBe(5);
        expect($review->comment)->toBe('Original comment');
    });
});

describe('Delete Review', function () {
    test('customer can delete their review', function () {
        $review = Review::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson(
            "/api/v1/reviews/{$review->id}",
            [],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertNoContent();
        expect(Review::withTrashed()->find($review->id)->deleted_at)->not->toBeNull();
    });

    test('customer cannot delete another customer\'s review', function () {
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson(
            "/api/v1/reviews/{$review->id}",
            [],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertForbidden();
    });
});
