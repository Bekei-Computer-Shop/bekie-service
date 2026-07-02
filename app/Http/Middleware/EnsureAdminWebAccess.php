<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || $user->is_banned || ! $user->is_admin || ! $user->hasRole('admin')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
