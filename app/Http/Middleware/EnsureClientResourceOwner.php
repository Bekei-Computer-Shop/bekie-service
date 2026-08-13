<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientResourceOwner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        $resource = $request->route($parameter);
        $user = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $resource || ! $user || (int) $resource->user_id !== (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: you do not have access to this resource.',
            ], 403);
        }

        return $next($request);
    }
}
