<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Services\JwtService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = \App\Models\User::factory()->create();
        $jti = Str::random(64);

        return [
            'user_id' => $user->id,
            'token' => hash('sha256', $jti),
            'refresh_token' => hash('sha256', Str::random(80)),
            'expires_at' => now()->addDays(60),
            'refresh_expires_at' => now()->addDays(60),
            'revoked' => false,
            'user_agent' => 'Mozilla/5.0',
            'ip_address' => '127.0.0.1',
            'scope' => 'client',
        ];
    }

    /**
     * Generate a valid JWT token for testing.
     * This method creates both the ApiToken record and returns the actual JWT.
     */
    public function withJwt()
    {
        return $this->afterCreating(function (ApiToken $token) {
            $jwtService = new JwtService;
            $payload = [
                'sub' => (string) $token->user_id,
                'jti' => Str::random(64),
                'scope' => 'client',
            ];
            $token->jwt_token = $jwtService->encode($payload, 60 * 24 * 60 * 60);
            // Update the token hash to match
            $token->update(['token' => hash('sha256', $payload['jti'])]);
        });
    }
}
