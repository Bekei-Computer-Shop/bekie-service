<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureClientResourceOwner;
use App\Http\Middleware\EnsureJsonResponse;
use App\Http\Middleware\LogAdminAction;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->api(append: [
            EnsureJsonResponse::class,
            ApiSecurityHeaders::class,
        ]);

        $middleware->alias([
            'owns-client-resource' => EnsureClientResourceOwner::class,
            'permission' => CheckPermission::class,
        ]);

        $middleware->appendToGroup('api', [LogAdminAction::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The ApiExceptionRenderer is registered for all API routes.
        $exceptions->renderable(app(ApiExceptionRenderer::class));
    })->create();
