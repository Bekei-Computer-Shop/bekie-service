<?php

namespace App\Http\Middleware;

use Closure;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Http\Request;

class ScrambleDocsAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (filter_var(env('SCRAMBLE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        return app(RestrictedDocsAccess::class)->handle($request, $next);
    }
}
