<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate. Use as `permission:<spatie-permission-name>`.
 *
 * Example:
 *     Route::middleware(['permission:users.create'])->post('users', ...);
 *
 * Resolves the authenticated user from `$request->user()` first, then falls
 * back to the `authenticated_user` request attribute populated by
 * AuthenticateAdminApiToken. Returns a 403 JSON response on failure.
 */
class CheckPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $user || ! method_exists($user, 'can') || ! $user->can($permission)) {
            return response()->json([
                'status' => 'error',
                'message' => "Forbidden: missing permission [{$permission}].",
            ], 403);
        }

        return $next($request);
    }
}
