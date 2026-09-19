<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'newuser@example.com')
            ->assertJsonPath('data.user.first_name', 'John')
            ->assertJsonPath('data.user.last_name', 'Doe')
            ->assertJsonPath('data.email_verification_required', true)
            ->assertJsonStructure([
                'data' => [
                    'email',
                    'email_verification_required',
                    'user' => [
                        'id',
                        'email',
                        'phone',
                        'first_name',
                        'last_name',
                        'name',
                        'role',
                        'is_active',
                        'is_banned',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_register_with_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+12345678901',
            'name' => 'Jane Smith',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.phone', '+12345678901')
            ->assertJsonPath('data.user.name', 'Jane Smith');
    }

    public function test_register_requires_email_or_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taken@example.com',
            'first_name' => 'John',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Email is already taken.');
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+12345678901']);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+12345678901',
            'name' => 'Jane Smith',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Phone is already taken.');
    }

    public function test_register_password_must_be_confirmed(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'DifferentPassword123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Authentication successful.')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_at',
                ],
            ]);
    }

    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_rejects_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->for($user)->create([
            'scope' => 'client',
            'revoked' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseHas('api_tokens', [
            'id' => $token->id,
            'revoked' => true,
        ]);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    public function test_refresh_token(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->for($user)->create([
            'scope' => 'client',
            'revoked' => false,
            'refresh_expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $token->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Token refreshed successfully.')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_at',
                ],
            ]);
    }

    public function test_refresh_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        ApiToken::factory()->for($user)->create([
            'scope' => 'client',
            'revoked' => false,
            'refresh_expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid.token.here',
        ]);

        $response->assertUnauthorized();
    }

    public function test_change_password_success(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $token = ApiToken::factory()->for($user)->create(['scope' => 'client']);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123',
                'new_password' => 'NewPassword456',
                'new_password_confirmation' => 'NewPassword456',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(
            Hash::check('NewPassword456', $user->fresh()->password)
        );
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $token = ApiToken::factory()->for($user)->create(['scope' => 'client']);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongPassword123',
                'new_password' => 'NewPassword456',
                'new_password_confirmation' => 'NewPassword456',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Current password is incorrect.');
    }

    public function test_change_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $token = ApiToken::factory()->for($user)->create(['scope' => 'client']);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123',
                'new_password' => 'NewPassword456',
                'new_password_confirmation' => 'DifferentPassword456',
            ]);

        $response->assertUnprocessable();
    }

    public function test_change_password_must_differ_from_current(): void
    {
        $user = User::factory()->create(['password' => 'SamePassword123']);
        $token = ApiToken::factory()->for($user)->create(['scope' => 'client']);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'SamePassword123',
                'new_password' => 'SamePassword123',
                'new_password_confirmation' => 'SamePassword123',
            ]);

        $response->assertUnprocessable();
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertUnauthorized();
    }
}
