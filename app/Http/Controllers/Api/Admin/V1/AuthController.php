<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $apiToken->delete();

        return $this->success(message: 'Admin logged out successfully.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $hashedRefreshToken = hash('sha256', $validated['refresh_token']);

        $apiToken = \App\Models\ApiToken::where('refresh_token', $hashedRefreshToken)
            ->where('scope', 'admin')
            ->first();

        if (! $apiToken || $apiToken->refresh_expires_at < now()) {
            return $this->error('Refresh token is invalid or expired.', 401);
        }

        $newTokenPair = $this->adminAuthService->createAdminToken($apiToken->user);

        $apiToken->delete();

        return $this->success([
            'access_token' => $newTokenPair['access_token'],
            'refresh_token' => $newTokenPair['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $newTokenPair['expires_at']->toDateTimeString(),
        ], 'Admin token refreshed successfully.');
    }
}
