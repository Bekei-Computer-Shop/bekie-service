<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\GoogleAuthRequest;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\AuthService;
use App\Services\GoogleAuthException;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shopper sign up / sign in with Google. The website sends the ID token it got
 * from Google Sign-In; we verify it ourselves and issue the same client-scope
 * tokens as email login. Separate from AuthController on purpose: the email
 * sign-up flow is untouched.
 */
class GoogleAuthController extends BaseApiController
{
    public function __construct(
        protected AuthService $authService,
        protected GoogleIdTokenVerifier $verifier,
    ) {}

    public function register(GoogleAuthRequest $request): JsonResponse
    {
        try {
            $profile = $this->verifier->verify($request->input('id_token'));
        } catch (GoogleAuthException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        $email = strtolower(trim($profile['email']));

        if (User::where('google_id', $profile['sub'])->exists() || User::where('email', $email)->exists()) {
            return $this->accountExists();
        }

        try {
            $user = DB::transaction(function () use ($profile, $email): User {
                $fullName = $profile['name'] ?? '';
                [$nameFirst, $nameLast] = array_pad(explode(' ', $fullName, 2), 2, '');

                $user = new User([
                    'email' => $email,
                    'google_id' => $profile['sub'],
                    'first_name' => $profile['given_name'] ?? ($nameFirst !== '' ? $nameFirst : Str::before($email, '@')),
                    'last_name' => $profile['family_name'] ?? ($nameLast !== '' ? $nameLast : null),
                    'avatar' => $profile['picture'],
                    'role' => 'user',
                    // The column is required; nobody knows this password, "forgot password" can set a real one.
                    'password' => Str::random(64),
                ]);
                // Google has already verified this email, so no OTP step.
                $user->email_verified_at = now();
                $user->save();

                $user->assignRole('user');

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // A soft-deleted account still owns the email, or a double-click raced us to it.
            return $this->accountExists();
        }

        app(AdminNotificationService::class)->newCustomer($user);

        return $this->created($this->signedIn($user, $request), 'Registration successful.');
    }

    public function login(GoogleAuthRequest $request): JsonResponse
    {
        try {
            $profile = $this->verifier->verify($request->input('id_token'));
        } catch (GoogleAuthException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        $user = User::where('google_id', $profile['sub'])->first();

        if (! $user) {
            $user = User::where('email', strtolower(trim($profile['email'])))->first();

            // Same email, different Google account (e.g. a reused Workspace address): not the same person.
            if ($user && $user->google_id !== null) {
                return $this->errorWithCode('google_account_mismatch', 'This email is linked to a different Google account.', 403);
            }
        }

        if (! $user) {
            return $this->errorWithCode('account_not_found', 'No account found for this Google email. Please sign up with Google first.', 404);
        }

        // Shopper site only: staff and admins sign in through the admin dashboard.
        if ($user->is_admin || $user->hasAnyRole(['admin', 'manager', 'staff'])) {
            return $this->error('Staff accounts sign in through the admin dashboard, not the store.', 403, code: 'staff_account');
        }

        if (! $user->is_active || $user->is_banned) {
            return $this->error('Your account has been restricted. Please contact customer support for help.', 403, code: 'account_restricted');
        }

        if ($user->google_id === null) {
            $this->linkGoogleAccount($user, $profile['sub']);
        }

        return $this->success($this->signedIn($user, $request), 'Authentication successful.');
    }

    private function linkGoogleAccount(User $user, string $googleId): void
    {
        $user->google_id = $googleId;

        // Whoever registered this email with a password never proved they own it, and
        // Google just did. Cut the old password off instead of leaving a way back in.
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->password = Str::random(64);
        }

        $user->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function signedIn(User $user, Request $request): array
    {
        $tokenPair = $this->authService->createToken($user, $request);

        return [
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
                'avatar' => $user->avatar,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'is_banned' => (bool) $user->is_banned,
            ],
        ];
    }

    private function accountExists(): JsonResponse
    {
        return $this->errorWithCode('account_exists', 'You already have an account. Please sign in with Google instead.', 409);
    }

    /**
     * Same envelope as error(), plus a stable `code` the website can switch on.
     */
    private function errorWithCode(string $code, string $message, int $status): JsonResponse
    {
        return $this->error($message, $status, [], $code);
    }
}
