<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticateAdminApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken)) {
            return ApiError::response('Please sign in to continue.', 401, code: 'unauthenticated');
        }

        try {
            $payload = (new JwtService)->decode($bearerToken);
        } catch (\Exception $e) {
            Log::warning('Admin JWT decoding failed: '.$e->getMessage());

            return $this->sessionExpired();
        }

        if (! $payload || ! isset($payload['jti']) || empty($payload['jti'])) {
            return $this->sessionExpired();
        }

        $apiToken = ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->where('scope', 'admin')
            ->first();

        // Check if the token exists and is not expired.
        // The `isExpired()` method on ApiToken model is assumed to be correct.
        if (! $apiToken || $apiToken->isExpired()) {
            return $this->sessionExpired();
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_admin || ! $user->is_active || $user->is_banned) {
            return ApiError::response(
                "This account can't use the admin panel. If you think this is a mistake, contact a super-admin.",
                403,
                code: 'account_restricted',
            );
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }

    /** The panel can refresh the token on this code before asking the user to sign in. */
    private function sessionExpired(): JsonResponse
    {
        return ApiError::response('Your session has expired. Please sign in again.', 401, code: 'session_expired');
    }
}
