<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Promotion;
use App\Models\ReportsCache;
use App\Models\Role;
use App\Models\TeamActivityLog;
use App\Models\User;
use App\Models\VisitorLog;
use App\Policies\AdminResourcePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tell Scramble to skip its default single-surface docs route — we
        // register two named surfaces (client + admin) in configureScramble()
        // and don't need the catch-all `/docs/api` endpoint.
        Scramble::ignoreDefaultRoutes();

        // Tell Spatie Permission to use our app/Models/{Role,Permission} classes
        // (which add SoftDeletes + guard_name=api defaults) instead of its
        // built-in ones.
        config([
            'permission.models.role' => Role::class,
            'permission.models.permission' => Permission::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $policies = [
            User::class => AdminResourcePolicy::class,
            Order::class => AdminResourcePolicy::class,
            Category::class => AdminResourcePolicy::class,
            Promotion::class => AdminResourcePolicy::class,
            ContentItem::class => AdminResourcePolicy::class,
            VisitorLog::class => AdminResourcePolicy::class,
            TeamActivityLog::class => AdminResourcePolicy::class,
            ReportsCache::class => AdminResourcePolicy::class,
            Role::class => RolePolicy::class,
            Permission::class => PermissionPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->configureScramble();
    }

    /**
     * Configure Scramble to expose two separate API surfaces (client + admin)
     * with a shared Bearer auth scheme. The admin surface is filtered to the
     * `/api/v1/admin/*` prefix; the client surface covers everything else
     * under `/api/v1`.
     */
    private function configureScramble(): void
    {
        // We register two APIs (client + admin) below, so disable the default
        // single-surface spec Scramble registers out of the box.
        Scramble::ignoreDefaultRoutes();

        // Client-facing API (customer + mobile web).
        Scramble::registerApi('client', [
            'api_path' => [
                'include' => ['api/v1'],
                'exclude' => ['api/v1/admin'],
            ],
            'info' => [
                'title' => 'Bekie Client API',
                'version' => env('API_VERSION', '1.0.0'),
                'description' => 'Customer-facing REST API. For the admin panel API, see `/docs/admin`.',
            ],
            // Scramble uses `ui.title` as the OpenAPI info title (see Generator.php).
            'ui' => [
                'title' => 'Bekie Client API',
            ],
            'security_strategy' => [
                MiddlewareAuthSecurityStrategy::class,
                [
                    'middleware' => ['App\\Http\\Middleware\\AuthenticateApiToken'],
                    'scheme' => SecurityScheme::http('bearer', 'JWT'),
                ],
            ],
        ])->expose(
            ui: fn ($router, $action) => $router->get('docs/client', $action)->name('scramble.docs.client.ui'),
            document: fn ($router, $action) => $router->get('docs/client.json', $action)->name('scramble.docs.client.document'),
        );

        // Admin-facing API (panel operators).
        Scramble::registerApi('admin', [
            'api_path' => 'api/v1/admin',
            'info' => [
                'title' => 'Bekie Admin API',
                'version' => env('API_VERSION', '1.0.0'),
                'description' => 'Admin panel REST API. Requires an admin-scoped bearer token. For the customer-facing API, see `/docs/client`.',
            ],
            'ui' => [
                'title' => 'Bekie Admin API',
            ],
            'security_strategy' => [
                MiddlewareAuthSecurityStrategy::class,
                [
                    'middleware' => ['App\\Http\\Middleware\\AuthenticateAdminApiToken'],
                    'scheme' => SecurityScheme::http('bearer', 'JWT'),
                ],
            ],
        ])->expose(
            ui: fn ($router, $action) => $router->get('docs/admin', $action)->name('scramble.docs.admin.ui'),
            document: fn ($router, $action) => $router->get('docs/admin.json', $action)->name('scramble.docs.admin.document'),
        );
    }
}
