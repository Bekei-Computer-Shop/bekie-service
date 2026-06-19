<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::where('token', hash('sha256', $bearer))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        if (!$token || !$token->user) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        if (!$token->user->is_active || $token->user->is_banned) {
            return response()->json(['message' => 'User account is restricted.'], 403);
        }

        // Manually log the user into the guard for the duration of the request
        Auth::setUser($token->user);

        // Share the token model instance if needed for logout
        $request->attributes->set('current_api_token', $token);

        return $next($request);
    }
}
