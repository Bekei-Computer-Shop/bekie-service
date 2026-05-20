<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization bearer token is required.',
            ], 401);
        }

        $apiToken = ApiToken::where('token', hash('sha256', $bearerToken))
            ->where('revoked', false)
            ->first();

        if (! $apiToken || $apiToken->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired access token.',
            ], 401);
        }

        Auth::setUser($apiToken->user);
        $request->setUserResolver(fn () => $apiToken->user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
