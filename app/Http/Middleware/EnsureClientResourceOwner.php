<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiError;
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
            return ApiError::response('You can only view or change your own items.', 403);
        }

        return $next($request);
    }
}
