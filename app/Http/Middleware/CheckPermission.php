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
 * Several permissions may be given, pipe-separated, and any one of them
 * admits the request — matching Spatie's own middleware syntax:
 *     Route::middleware(['permission:orders.update|orders.approve'])
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

        $accepted = array_filter(explode('|', $permission), static fn (string $name): bool => $name !== '');

        $allowed = $user
            && method_exists($user, 'can')
            && array_filter($accepted, static fn (string $name): bool => $user->can($name)) !== [];

        if (! $allowed) {
            $named = implode('] or [', $accepted);

            return response()->json([
                'status' => 'error',
                'message' => "Forbidden: missing permission [{$named}].",
            ], 403);
        }

        return $next($request);
    }
}
