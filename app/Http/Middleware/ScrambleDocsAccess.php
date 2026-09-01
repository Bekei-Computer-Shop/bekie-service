<?php

namespace App\Http\Middleware;

use Closure;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Http\Request;

class ScrambleDocsAccess
{
    public function handle(Request $request, Closure $next)
    {
        $scrambleEnabled = env('SCRAMBLE_ENABLED');

        if ($scrambleEnabled === null) {
            return $next($request);
        }

        if (filter_var($scrambleEnabled, FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        return app(RestrictedDocsAccess::class)->handle($request, $next);
    }
}
