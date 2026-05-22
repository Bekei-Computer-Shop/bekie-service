<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiToken = $request->attributes->get('api_token');
        $user = $request->attributes->get('authenticated_user');

        if (! $user || ! $user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
