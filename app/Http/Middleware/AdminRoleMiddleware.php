<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user || ! $user->is_active || $user->is_banned || ! $user->is_admin || ! $user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
