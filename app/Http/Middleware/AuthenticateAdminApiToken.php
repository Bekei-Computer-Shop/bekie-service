<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdminApiToken
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

        $payload = (new JwtService)->decode($bearerToken);

        if (! $payload || ! isset($payload['jti'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired admin access token.',
            ], 401);
        }

        $apiToken = ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->where('scope', 'admin')
            ->first();

        if (! $apiToken || $apiToken->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired admin access token.',
            ], 401);
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_admin || ! $user->is_active || $user->is_banned) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Admin access required.',
            ], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }
}
