<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApiToken $token;

    private Wishlist $wishlist;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $jti = Str::random(64);
        $this->token = ApiToken::factory()->for($this->user)->create([
            'scope' => 'client',
            'token' => hash('sha256', $jti),
        ]);

        $jwtService = new JwtService;
        $this->jwtToken = $jwtService->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 24 * 60 * 60);

        $this->wishlist = Wishlist::factory()->for($this->user)->create();
    }

    public function test_get_wishlist_success(): void
    {
        $product = Product::factory()->create();
        WishlistItem::create([
            'wishlist_id' => $this->wishlist->id,
            'product_id' => $product->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/wishlist');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $this->wishlist->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_create_or_update_wishlist(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->putJson('/api/v1/wishlist', [
                'name' => 'My Favorite Items',
                'is_public' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_add_item_to_wishlist(): void
    {
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/wishlist/items', [
                'product_id' => $product->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.items.0.product_id', $product->id);

        $this->assertDatabaseHas('wishlist_items', [
            'wishlist_id' => $this->wishlist->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_add_item_requires_valid_product(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/wishlist/items', [
                'product_id' => 9999,
            ]);

        $response->assertUnprocessable();
    }

    public function test_remove_item_from_wishlist(): void
    {
        $product = Product::factory()->create();
        $item = WishlistItem::create([
            'wishlist_id' => $this->wishlist->id,
            'product_id' => $product->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->deleteJson("/api/v1/wishlist/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('wishlist_items', [
            'id' => $item->id,
        ]);
    }

    public function test_check_product_in_wishlist(): void
    {
        $product = Product::factory()->create();
        WishlistItem::create([
            'wishlist_id' => $this->wishlist->id,
            'product_id' => $product->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/wishlist/check?product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.exists', true);
    }

    public function test_check_product_not_in_wishlist(): void
    {
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/wishlist/check?product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.exists', false);
    }

    public function test_delete_wishlist(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->deleteJson('/api/v1/wishlist');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('wishlists', [
            'id' => $this->wishlist->id,
        ]);
    }

    public function test_wishlist_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/wishlist');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/v1/wishlist');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/v1/wishlist/items', []);
        $response->assertUnauthorized();

        $response = $this->deleteJson('/api/v1/wishlist');
        $response->assertUnauthorized();
    }

    public function test_remove_nonexistent_item(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->deleteJson('/api/v1/wishlist/items/9999');

        $response->assertNotFound();
    }
}
