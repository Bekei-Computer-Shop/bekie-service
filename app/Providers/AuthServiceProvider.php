<?php

declare(strict_types=1);

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
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
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

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
