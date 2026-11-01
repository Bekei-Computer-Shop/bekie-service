<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user || ! $user->is_active || $user->is_banned || ! $user->is_admin || ! $user->hasRole('admin')) {
            return ApiError::response(
                'Only administrators can do this. Ask a super-admin if you need access.',
                403,
                code: 'permission_denied',
            );
        }

        return $next($request);
    }
}
