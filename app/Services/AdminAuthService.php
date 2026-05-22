<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminAuthService
{
    public function authenticateAdmin(string $email, string $password): ?array
    {
        $user = User::where('email', $email)
            ->where('is_admin', true)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        if (! $user->hasRole('admin')) {
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
        $accessToken = hash('sha256', bin2hex(random_bytes(32)));
        $refreshToken = hash('sha256', bin2hex(random_bytes(32)));
        $expiresAt = Carbon::now()->addMinutes(120);
        $refreshExpiresAt = Carbon::now()->addDays(30);

        ApiToken::create([
            'user_id' => $user->id,
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'scope' => 'admin',
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function validateAdminToken(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);

        $apiToken = ApiToken::where('token', $hashedToken)
            ->where('scope', 'admin')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $apiToken) {
            return null;
        }

        return $apiToken->user;
    }
}
