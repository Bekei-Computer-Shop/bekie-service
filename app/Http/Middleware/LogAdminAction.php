<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogAdminAction
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        /** @var User|null $actor */
        $actor = $request->attributes->get('authenticated_user');

        if (! $actor instanceof User || $response->status() >= 400) {
            return $response;
        }

        $action = $this->resolveAction($request);
        [$targetType, $targetId] = $this->resolveTarget($request);

        ActivityLog::record(
            $actor,
            $action,
            $targetType,
            $targetId,
            $request->ip(),
            $request->userAgent(),
        );

        return $response;
    }

    private function resolveAction(Request $request): string
    {
        return match ($request->method()) {
            'GET' => 'viewed',
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => strtolower($request->method()),
        };
    }

    private function resolveTarget(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if (is_numeric($value)) {
                return [Str::studly(Str::singular($key)), (int) $value];
            }

            if (is_object($value) && property_exists($value, 'getKey')) {
                return [class_basename($value), (int) $value->getKey()];
            }
        }

        return [null, null];
    }
}
