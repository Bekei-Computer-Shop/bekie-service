<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\ReportsCache;
use App\Models\TeamActivityLog;
use App\Models\User;
use App\Models\VisitorLog;
use App\Policies\AdminResourcePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
            Role::class => AdminResourcePolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
