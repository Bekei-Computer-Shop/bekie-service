<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->admin->assignRole('admin');

    $this->token = ApiToken::factory()->create([
        'user_id' => $this->admin->id,
        'scope' => 'admin',
    ]);

    $this->product = Product::factory()->create();
    $this->customer = User::factory()->create();
});

describe('List Reviews', function () {
    test('admin can list all reviews', function () {
        Review::factory()->count(10)->create(['product_id' => $this->product->id]);

        $response = $this->getJson(
            '/api/v1/admin/reviews',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(10);
        expect($response['data']['pagination']['total'])->toBe(10);
    });

    test('admin can paginate reviews', function () {
        Review::factory()->count(30)->create(['product_id' => $this->product->id]);

        $response = $this->getJson(
            '/api/v1/admin/reviews?page=2&per_page=10',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(10);
        expect($response['data']['pagination']['current_page'])->toBe(2);
        expect($response['data']['pagination']['total'])->toBe(30);
    });

    test('admin can filter reviews by status', function () {
        Review::factory()->count(5)->approved()->create(['product_id' => $this->product->id]);
        Review::factory()->count(3)->rejected()->create(['product_id' => $this->product->id]);
        Review::factory()->count(2)->create(['product_id' => $this->product->id, 'status' => 'pending']);

        $response = $this->getJson(
            '/api/v1/admin/reviews?status=approved',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(5);
        expect($response['data']['items'][0]['status'])->toBe('approved');
    });

    test('admin can filter reviews by rating', function () {
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 3]);

        $response = $this->getJson(
            '/api/v1/admin/reviews?rating=5',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(2);
        expect($response['data']['items'][0]['rating'])->toBe(5);
    });

    test('admin can filter reviews by product', function () {
        $product2 = Product::factory()->create();
        Review::factory()->count(3)->create(['product_id' => $this->product->id]);
        Review::factory()->count(2)->create(['product_id' => $product2->id]);

        $response = $this->getJson(
            "/api/v1/admin/reviews?product_id={$this->product->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(3);
    });

    test('admin can filter reviews by customer', function () {
        $customer2 = User::factory()->create();
        Review::factory()->create(['user_id' => $this->customer->id, 'product_id' => $this->product->id]);
        Review::factory()->create(['user_id' => $customer2->id, 'product_id' => $this->product->id]);

        $response = $this->getJson(
            "/api/v1/admin/reviews?customer_id={$this->customer->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(1);
        expect($response['data']['items'][0]['customer']['id'])->toBe($this->customer->id);
    });

    test('admin can search reviews by comment/title/customer', function () {
        Review::factory()->create([
            'product_id' => $this->product->id,
            'comment' => 'Excellent product quality',
            'user_id' => $this->customer->id,
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'comment' => 'Poor quality',
        ]);

        $response = $this->getJson(
            '/api/v1/admin/reviews?q=Excellent',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'])->toHaveCount(1);
        expect($response['data']['items'][0]['comment'])->toContain('Excellent');
    });

    test('admin can sort reviews by rating descending', function () {
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 2]);
        Review::factory()->create(['product_id' => $this->product->id, 'rating' => 4]);

        $response = $this->getJson(
            '/api/v1/admin/reviews?sort=rating&direction=desc',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['items'][0]['rating'])->toBe(5);
        expect($response['data']['items'][1]['rating'])->toBe(4);
        expect($response['data']['items'][2]['rating'])->toBe(2);
    });
});

describe('View Review Details', function () {
    test('admin can view full review details', function () {
        $review = Review::factory()->verifiedPurchase()->create(['product_id' => $this->product->id]);

        $response = $this->getJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $review->id);
        $response->assertJsonPath('data.is_verified_purchase', true);
        $response->assertJsonPath('data.helpful_count', 0);
    });

    test('admin sees customer information in review', function () {
        $review = Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
        ]);

        $response = $this->getJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.customer.email', $this->customer->email);
    });

    test('admin sees product information in review', function () {
        $review = Review::factory()->create(['product_id' => $this->product->id]);

        $response = $this->getJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.product.sku', $this->product->sku);
    });
});

describe('Moderate Review', function () {
    test('admin can approve pending review', function () {
        $review = Review::factory()->create([
            'product_id' => $this->product->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['status' => 'approved'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['status'])->toBe('approved');

        $review->refresh();
        expect($review->status)->toBe('approved');
    });

    test('admin can reject review', function () {
        $review = Review::factory()->create([
            'product_id' => $this->product->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['status' => 'rejected'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['status'])->toBe('rejected');
    });

    test('admin can hide inappropriate review', function () {
        $review = Review::factory()->approved()->create(['product_id' => $this->product->id]);

        $response = $this->patchJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['status' => 'hidden'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['status'])->toBe('hidden');
    });

    test('admin can feature review', function () {
        $review = Review::factory()->approved()->create(['product_id' => $this->product->id]);

        $response = $this->patchJson(
            "/api/v1/admin/reviews/{$review->id}",
            ['is_featured' => true],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['is_featured'])->toBeTrue();

        $review->refresh();
        expect($review->is_featured)->toBeTrue();
    });

    test('admin can update multiple fields', function () {
        $review = Review::factory()->create(['product_id' => $this->product->id]);

        $response = $this->patchJson(
            "/api/v1/admin/reviews/{$review->id}",
            [
                'status' => 'approved',
                'is_featured' => true,
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect($response['data']['status'])->toBe('approved');
        expect($response['data']['is_featured'])->toBeTrue();
    });
});

describe('Delete Review', function () {
    test('admin can permanently delete review', function () {
        $review = Review::factory()->create(['product_id' => $this->product->id]);

        $response = $this->deleteJson(
            "/api/v1/admin/reviews/{$review->id}",
            [],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        expect(Review::find($review->id))->toBeNull();
    });
});

describe('Authorization', function () {
    test('unauthenticated user cannot access admin reviews', function () {
        $response = $this->getJson('/api/v1/admin/reviews');

        $response->assertUnauthorized();
    });

    test('client user cannot access admin reviews', function () {
        $client = User::factory()->create();
        $clientToken = ApiToken::factory()->create([
            'user_id' => $client->id,
            'scope' => 'client',
        ]);

        $response = $this->getJson(
            '/api/v1/admin/reviews',
            ['Authorization' => 'Bearer '.$clientToken->token]
        );

        $response->assertForbidden();
    });

    test('non-admin user cannot access admin reviews', function () {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $token = ApiToken::factory()->create([
            'user_id' => $nonAdmin->id,
            'scope' => 'admin',
        ]);

        $response = $this->getJson(
            '/api/v1/admin/reviews',
            ['Authorization' => 'Bearer '.$token->token]
        );

        $response->assertForbidden();
    });
});
