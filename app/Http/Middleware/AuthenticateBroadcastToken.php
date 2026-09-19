<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBroadcastToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();
        try {
            $payload = $bearerToken ? (new JwtService)->decode($bearerToken) : null;
        } catch (\Throwable) {
            $payload = null;
        }
        $apiToken = $payload['jti'] ?? null
            ? ApiToken::where('token', hash('sha256', $payload['jti']))
                ->where('revoked', false)
                ->whereIn('scope', ['client', 'admin'])
                ->first()
            : null;

        if (! $apiToken || $apiToken->isExpired() || ! $apiToken->user?->is_active || $apiToken->user?->is_banned) {
            return response()->json(['message' => 'Invalid or expired access token.'], 401);
        }

        Auth::setUser($apiToken->user);
        $request->setUserResolver(fn () => $apiToken->user);
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('authenticated_user', $apiToken->user);

        return $next($request);
    }
}
