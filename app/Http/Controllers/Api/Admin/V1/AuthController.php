<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\ApiToken;
use App\Services\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseAdminController
{
    public function __construct(protected AdminAuthService $adminAuthService) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tokenPair = $this->adminAuthService->authenticateAdmin($validated['email'], $validated['password']);

        if (! $tokenPair) {
            return $this->error('Invalid admin credentials.', 401);
        }

        return $this->success($tokenPair, 'Admin authentication successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $apiToken = $request->attributes->get('api_token');

        if (! $apiToken) {
            return $this->error('Unauthorized.', 401);
        }

        $apiToken->revoke();

        return $this->success(message: 'Admin logged out successfully.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $hashedRefreshToken = hash('sha256', $validated['refresh_token']);

        $apiToken = ApiToken::where('refresh_token', $hashedRefreshToken)
            ->where('scope', 'admin')
            ->where('revoked', false)
            ->first();

        if (! $apiToken || $apiToken->isRefreshExpired()) {
            return $this->error('Refresh token is invalid or expired.', 401);
        }

        $newTokenPair = $this->adminAuthService->createAdminToken($apiToken->user);

        $apiToken->revoke();

        return $this->success([
            'access_token' => $newTokenPair['access_token'],
            'refresh_token' => $newTokenPair['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $newTokenPair['expires_at']->toDateTimeString(),
        ], 'Admin token refreshed successfully.');
    }
}
