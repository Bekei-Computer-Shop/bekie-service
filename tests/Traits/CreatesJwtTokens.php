<?php

namespace Tests\Traits;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Support\Str;

trait CreatesJwtTokens
{
    /**
     * Create a JWT token for a user and return both the token model and JWT string.
     */
    protected function createJwtTokenFor(User $user, string $scope = 'client'): array
    {
        $jti = Str::random(64);
        $token = ApiToken::factory()->for($user)->create([
            'scope' => $scope,
            'token' => hash('sha256', $jti),
        ]);

        $jwtService = new JwtService;
        $jwt = $jwtService->encode([
            'sub' => (string) $user->id,
            'jti' => $jti,
            'scope' => $scope,
        ], 60 * 24 * 60 * 60);

        return [
            'model' => $token,
            'jwt' => $jwt,
        ];
    }
}
