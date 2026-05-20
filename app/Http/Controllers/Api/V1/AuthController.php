<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RefreshTokenRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    public function __construct(protected AuthService $authService) {}

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
