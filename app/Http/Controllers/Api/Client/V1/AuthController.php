<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\LoginRequest;
use App\Http\Requests\Api\Client\V1\RefreshTokenRequest;
use App\Http\Requests\Api\Client\V1\RegisterRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    public function __construct(protected AuthService $authService) {}

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $email = isset($data['email']) ? strtolower(trim($data['email'])) : null;
        $phone = isset($data['phone']) ? trim($data['phone']) : null;

        // Race-condition guard: turn a unique-index violation into a clean 422.
        if ($email && User::where('email', $email)->exists()) {
            return $this->error('Email is already taken.', 422);
        }
        if ($phone && User::where('phone', $phone)->exists()) {
            return $this->error('Phone is already taken.', 422);
        }

        $user = DB::transaction(function () use ($data, $email, $phone) {
            return User::create([
                'email' => $email,
                'phone' => $phone,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'name' => $data['name'] ?? null, // mutator fills first/last
                'password' => $data['password'], // 'hashed' cast hashes on save
            ]);
        });

        $tokenPair = $this->authService->createToken($user, $request);

        return $this->created([
            'access_token' => $tokenPair['access_token'],
            'refresh_token' => $tokenPair['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenPair['expires_at']->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'is_banned' => (bool) $user->is_banned,
            ],
        ], 'Registration successful.');
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        $tokenPair = $this->authService->createToken($user, $request);

        return $this->success([
            'access_token' => $tokenPair['access_token'],
            'refresh_token' => $tokenPair['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenPair['expires_at']->toDateTimeString(),
        ], 'Authentication successful.');
    }

    public function logout(Request $request)
    {
        $apiToken = $request->attributes->get('api_token');

        if (! $apiToken instanceof ApiToken) {
            return $this->error('Unauthorized.', 401);
        }

        $this->authService->revokeToken($apiToken);

        return $this->success(message: 'Logged out successfully.');
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $token = $this->authService->refreshToken($request->input('refresh_token'), $request);

        if (! $token) {
            return $this->error('Refresh token is invalid or expired.', 401);
        }

        return $this->success([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at']->toDateTimeString(),
        ], 'Token refreshed successfully.');
    }
}
