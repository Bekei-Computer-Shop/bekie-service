<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Address;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $jti = Str::random(64);
        ApiToken::factory()->for($this->user)->create([
            'scope' => 'client',
            'token' => hash('sha256', $jti),
        ]);
        $this->jwtToken = (new JwtService)->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 24 * 60 * 60);
    }

    public function test_first_address_becomes_default_and_list_shows_default_first(): void
    {
        $first = $this->authenticated()->postJson('/api/v1/addresses', $this->addressPayload());

        $first->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.country', 'KH');

        $second = $this->authenticated()->postJson('/api/v1/addresses', $this->addressPayload([
            'label' => 'Office',
            'address_line_1' => '2 Street 200',
        ]));

        $second->assertCreated()->assertJsonPath('data.is_default', false);

        $this->authenticated()->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_default_can_be_changed_and_delete_promotes_newest_remaining_address(): void
    {
        $first = $this->user->addresses()->create($this->addressPayload() + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => true,
        ]);
        $second = $this->user->addresses()->create($this->addressPayload([
            'label' => 'Office',
            'address_line_1' => '2 Street 200',
        ]) + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->authenticated()->postJson("/api/v1/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', ['id' => $first->id, 'is_default' => false]);

        $this->authenticated()->deleteJson("/api/v1/addresses/{$second->id}")->assertNoContent();

        $this->assertSoftDeleted('addresses', ['id' => $second->id]);
        $this->assertDatabaseHas('addresses', ['id' => $first->id, 'is_default' => true]);
    }

    public function test_address_endpoints_hide_foreign_and_deleted_addresses(): void
    {
        $owned = $this->user->addresses()->create($this->addressPayload() + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => true,
        ]);
        $foreign = User::factory()->create()->addresses()->create($this->addressPayload() + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => true,
        ]);
        $deleted = $this->user->addresses()->create($this->addressPayload(['label' => 'Old']) + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => false,
        ]);
        $deleted->delete();

        $this->authenticated()->getJson("/api/v1/addresses/{$owned->id}")->assertOk();
        $this->authenticated()->getJson("/api/v1/addresses/{$foreign->id}")->assertNotFound();
        $this->authenticated()->patchJson("/api/v1/addresses/{$foreign->id}", ['label' => 'Changed'])->assertNotFound();
        $this->authenticated()->deleteJson("/api/v1/addresses/{$deleted->id}")->assertNotFound();
        $this->authenticated()->getJson('/api/v1/addresses/999999')->assertNotFound();
    }

    public function test_default_uniqueness_is_enforced_by_database(): void
    {
        $first = $this->user->addresses()->create($this->addressPayload() + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => true,
        ]);
        $second = $this->user->addresses()->create($this->addressPayload(['label' => 'Office']) + [
            'type' => 'shipping',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->expectException(QueryException::class);
        Address::query()->whereKey($second->id)->update(['is_default' => true]);
    }

    public function test_address_management_requires_authentication(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', $this->addressPayload())->assertUnauthorized();
    }

    private function authenticated(): static
    {
        return $this->withHeader('Authorization', "Bearer {$this->jwtToken}");
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Sana Customer',
            'phone' => '+85512345678',
            'address_line_1' => '12 Street 240',
            'city' => 'Phnom Penh',
            'postal_code' => '12000',
            'country' => 'kh',
            'label' => 'Home',
        ], $overrides);
    }
}
