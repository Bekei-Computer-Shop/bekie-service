<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends BaseAdminController
{
    public function __construct(protected AdminAuthService $adminAuthService) {}

    /**
     * Shape the authenticated admin into the profile payload the panel consumes.
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames(),
        ];
    }

    /** GET /admin/auth/me — current admin profile. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        return $this->success($this->profilePayload($user), 'Profile retrieved successfully.');
    }

    /** PATCH /admin/auth/profile — update own personal information. */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $user->fill($validated);
        $user->save();

        return $this->success($this->profilePayload($user->fresh()), 'Profile updated successfully.');
    }

    /** POST /admin/auth/change-password — change own password. */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->error('The current password is incorrect.', 422, [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = $validated['new_password'];
        $user->save();

        return $this->success(message: 'Password changed successfully.');
    }

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
