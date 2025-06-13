<?php

namespace App\Providers;

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GlobalPermissionService::class);
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
            \Spatie\Permission\Events\RoleAttached::class,
            \App\Listeners\Permissions\RoleAttachedListener::class
        );

        Event::listen(
            \Spatie\Permission\Events\RoleDetached::class,
            \App\Listeners\Permissions\RoleDetachedListener::class
        );

        Event::listen(
            \Spatie\Permission\Events\PermissionAttached::class,
            \App\Listeners\Permissions\PermissionAttachedListener::class
        );

        Event::listen(
            \Spatie\Permission\Events\PermissionDetached::class,
            \App\Listeners\Permissions\PermissionDetachedListener::class
        );
    }
}
