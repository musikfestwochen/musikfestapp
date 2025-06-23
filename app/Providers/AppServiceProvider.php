<?php

namespace App\Providers;

use App\Listeners\Permissions\PermissionAttachedListener;
use App\Listeners\Permissions\PermissionDetachedListener;
use App\Listeners\Permissions\RoleAttachedListener;
use App\Listeners\Permissions\RoleDetachedListener;
use App\Models\User;
use App\Services\GlobalPermissionService;
use App\Services\Peoplecount\SensorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GlobalPermissionService::class);
        $this->app->singleton(SensorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();

        Gate::before(function (User $user, string $ability): ?bool {
            return GlobalPermissionService::canGlobally($user, $ability);
        });

        // Register event listeners directly using the Event facade
        Event::listen(
            RoleAttached::class,
            RoleAttachedListener::class
        );

        Event::listen(
            RoleDetached::class,
            RoleDetachedListener::class
        );

        Event::listen(
            PermissionAttached::class,
            PermissionAttachedListener::class
        );

        Event::listen(
            PermissionDetached::class,
            PermissionDetachedListener::class
        );
    }
}
