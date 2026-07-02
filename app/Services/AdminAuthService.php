<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthService
{
    public function authenticateAdmin(string $email, string $password): ?array
    {
        $user = User::where('email', $email)
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->hasRole('admin')) {
            return null;
        }

        $token = $this->createAdminToken($user);

        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at']->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ];
    }

    public function createAdminToken(User $user): array
    {
        $jti = Str::random(64);
        $refreshToken = Str::random(80);
        $expiresAt = Carbon::now()->addMinutes(120);
        $refreshExpiresAt = Carbon::now()->addDays(30);

        $jwt = (new JwtService)->encode([
            'iss' => config('app.url'),
            'aud' => request()->getHost(),
            'sub' => (string) $user->id,
            'jti' => $jti,
            'scope' => 'admin',
        ], 120 * 60);

        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $jti),
            'refresh_token' => hash('sha256', $refreshToken),
            'expires_at' => $expiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'revoked' => false,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'scope' => 'admin',
        ]);

        return [
            'access_token' => $jwt,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function validateAdminToken(string $token): ?User
    {
        $payload = (new JwtService)->decode($token);

        if (! $payload || ! isset($payload['jti'])) {
            return null;
        }

        $hashed = hash('sha256', $payload['jti']);

        $apiToken = ApiToken::where('token', $hashed)
            ->where('scope', 'admin')
            ->where('revoked', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $apiToken) {
            return null;
        }

        return $apiToken->user;
    }
}
