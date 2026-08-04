<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
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
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization bearer token is required.',
            ], 401);
        }

        $payload = (new JwtService)->decode($bearerToken);

        if (! $payload || ! isset($payload['jti'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired access token.',
            ], 401);
        }

        $apiToken = ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->where('scope', 'client')
            ->first();

        if (! $apiToken || $apiToken->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired access token.',
            ], 401);
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_active || $user->is_banned) {
            return response()->json([
                'status' => 'error',
                'message' => 'User account is restricted.',
            ], 403);
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
}
