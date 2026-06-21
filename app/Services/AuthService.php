<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuthService
{
    public function createToken(User $user, Request $request): array
    {
        $jti = Str::random(64);
        $refreshToken = Str::random(80);

        $jwt = (new JwtService)->encode([
            'iss' => config('app.url'),
            'aud' => $request->getHost(),
            'sub' => (string) $user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 60);

        $token = ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $jti),
            'refresh_token' => hash('sha256', $refreshToken),
            'expires_at' => Carbon::now()->addMinutes(60),
            'refresh_expires_at' => Carbon::now()->addDays(7),
            'revoked' => false,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?: 'api',
        ]);

        return [
            'model' => $token,
            'access_token' => $jwt,
            'refresh_token' => $refreshToken,
            'expires_at' => $token->expires_at,
        ];
    }

    public function refreshToken(string $refreshToken, Request $request): ?array
    {
        $token = ApiToken::where('refresh_token', hash('sha256', $refreshToken))
            ->where('revoked', false)
            ->first();

        if (! $token || $token->isRefreshExpired()) {
            return null;
        }

        $jti = Str::random(64);
        $newRefreshToken = Str::random(80);

        $jwt = (new JwtService)->encode([
            'iss' => config('app.url'),
            'aud' => $request->getHost(),
            'sub' => (string) $token->user_id,
            'jti' => $jti,
            'scope' => $token->scope ?? 'client',
        ], 60 * 60);

        $token->update([
            'token' => hash('sha256', $jti),
            'refresh_token' => hash('sha256', $newRefreshToken),
            'expires_at' => Carbon::now()->addMinutes(60),
            'refresh_expires_at' => Carbon::now()->addDays(7),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?: 'api',
        ]);

        return [
            'model' => $token->fresh(),
            'access_token' => $jwt,
            'refresh_token' => $newRefreshToken,
            'expires_at' => $token->expires_at,
        ];
    }

    public function findActiveToken(string $token): ?ApiToken
    {
        $payload = (new JwtService)->decode($token);

        if (! $payload || ! isset($payload['jti'])) {
            return null;
        }

        return ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->first();
    }

    public function revokeToken(ApiToken $token): bool
    {
        return $token->revoke();
    }
}
