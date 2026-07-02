<?php

namespace App\Services\Admin;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_admin || ! $user->is_active || $user->is_banned) {
            throw ValidationException::withMessages([
                'email' => ['This account is not authorized to access the admin panel.'],
            ]);
        }

        return $this->generateTokenPair($user, 'admin');
    }

    public function generateTokenPair(User $user, string $scope): array
    {
        $rawAccessToken = Str::random(60);
        $rawRefreshToken = Str::random(60);

        $token = ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawAccessToken),
            'refresh_token' => hash('sha256', $rawRefreshToken),
            'expires_at' => now()->addMinutes(120),
            'refresh_expires_at' => now()->addDays(30),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'revoked' => false,
            'scope' => $scope,
        ]);

        return [
            'access_token' => $rawAccessToken,
            'refresh_token' => $rawRefreshToken,
            'expires_in' => 120 * 60,
            'token_type' => 'Bearer',
            'user' => $user->load('roles.permissions'),
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $token = ApiToken::where('refresh_token', hash('sha256', $refreshToken))
            ->where('revoked', false)
            ->where('refresh_expires_at', '>', now())
            ->firstOrFail();

        $user = $token->user;

        // Revoke old token pair
        $token->update(['revoked' => true]);

        return $this->generateTokenPair($user, $token->scope);
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Optional: Revoke all other tokens for security
        ApiToken::where('user_id', $user->id)->update(['revoked' => true]);
    }
}
