<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Permissions\PermissionAttachedListener;
use App\Listeners\Permissions\PermissionDetachedListener;
use App\Listeners\Permissions\RoleAttachedListener;
use App\Listeners\Permissions\RoleDetachedListener;
use App\Models\StageSafety\Sensor as StageSafetySensor;
use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();

        // Reject unknown fields in all FormRequests globally (#115)
        FormRequest::failOnUnknownFields();

        URL::forceHttps(app()->isProduction());

        RateLimiter::for('stage-safety-readings', function (Request $request): Limit {
            $sensor = auth('sanctum')->user();
            $tokenId = $sensor instanceof StageSafetySensor
                ? $sensor->currentAccessToken()->getKey()
                : null;

            return Limit::perMinute(60)->by(
                $tokenId === null ? 'ip:'.$request->ip() : 'token:'.$tokenId,
            );
        });

        Gate::before(function (User $user, string $ability): ?bool {
            return GlobalPermissionService::canGlobally($user, $ability);
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->can('admin.pulse');
        });

        Gate::define('viewLogViewer', function (User $user) {
            return $user->can('admin.logs');
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
