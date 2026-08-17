<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticateAdminApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken)) {
            throw new AuthenticationException('Authorization bearer token is required.');
        }

        try {
            $payload = (new JwtService)->decode($bearerToken);
        } catch (\Exception $e) {
            Log::warning('Admin JWT decoding failed: '.$e->getMessage());
            throw new AuthenticationException('Invalid or expired admin access token.');
        }

        if (! $payload || ! isset($payload['jti']) || empty($payload['jti'])) {
            throw new AuthenticationException('Invalid or malformed admin access token payload.');
        }

        $apiToken = ApiToken::where('token', hash('sha256', $payload['jti']))
            ->where('revoked', false)
            ->where('scope', 'admin')
            ->first();

        // Check if the token exists and is not expired.
        // The `isExpired()` method on ApiToken model is assumed to be correct.
        if (! $apiToken || $apiToken->isExpired()) {
            throw new AuthenticationException('Invalid or expired admin access token.');
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_admin || ! $user->is_active || $user->is_banned) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: User account is not valid for admin access.',
            ], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }
}
