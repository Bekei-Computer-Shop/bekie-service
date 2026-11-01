<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return ApiError::response('Please sign in to continue.', 401, code: 'unauthenticated');
        }

        $payload = (new JwtService)->decode($bearerToken);

        if (! $payload || ! isset($payload['jti'])) {
            return $this->sessionExpired();
        }

        $apiToken = ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->where('scope', 'client')
            ->first();

        if (! $apiToken || $apiToken->isExpired()) {
            return $this->sessionExpired();
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_active || $user->is_banned) {
            return ApiError::response(
                'Your account has been restricted. Please contact customer support for help.',
                403,
                code: 'account_restricted',
            );
        }

        // Manually log the user into the guard for the duration of the request
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Share the token model instance for downstream handlers (e.g. logout).
        // Use the canonical 'api_token' key so AuthController::logout can find it.
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }

    /** The app can refresh the token on this code before asking the user to sign in. */
    private function sessionExpired(): Response
    {
        return ApiError::response('Your session has expired. Please sign in again.', 401, code: 'session_expired');
    }
}
