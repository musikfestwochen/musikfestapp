<?php

namespace App\Providers;

use App\Listeners\Permissions\PermissionAttachedListener;
use App\Listeners\Permissions\PermissionDetachedListener;
use App\Listeners\Permissions\RoleAttachedListener;
use App\Listeners\Permissions\RoleDetachedListener;
use App\Models\User;
use App\Services\GlobalPermissionService;
use App\Services\Peoplecount\AlertService;
use App\Services\Peoplecount\AreaAggregationService;
use App\Services\Peoplecount\AreaResetService;
use App\Services\Peoplecount\AreaService;
use App\Services\Peoplecount\AssignmentService;
use App\Services\Peoplecount\EventService;
use App\Services\Peoplecount\IntervalCountService;
use App\Services\Peoplecount\SensorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GlobalPermissionService::class);
        $this->app->singleton(SensorService::class);
        $this->app->singleton(IntervalCountService::class);
        $this->app->singleton(EventService::class);
        $this->app->singleton(AreaService::class);
        $this->app->singleton(AreaResetService::class);
        $this->app->singleton(AssignmentService::class);
        $this->app->singleton(AreaAggregationService::class);
        $this->app->singleton(AlertService::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Model::automaticallyEagerLoadRelationships();

        URL::forceHttps(app()->isProduction());

        Gate::before(function (User $user, string $ability): ?bool {
            return GlobalPermissionService::canGlobally($user, $ability);
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->can('admin.pulse');
        });

        // Register event listeners directly using the Event facade
        Event::listen(
            RoleAttachedEvent::class,
            RoleAttachedListener::class
        );

        Event::listen(
            RoleDetachedEvent::class,
            RoleDetachedListener::class
        );

        Event::listen(
            PermissionAttachedEvent::class,
            PermissionAttachedListener::class
        );

        Event::listen(
            PermissionDetachedEvent::class,
            PermissionDetachedListener::class
        );
    }
}
