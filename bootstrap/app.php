<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Permissions\GlobalOrganizationMiddleware;
use App\Http\Middleware\Permissions\OrganizationSlugMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias(
            ['permissions.global_organization' => GlobalOrganizationMiddleware::class,
                'permissions.organization_slug' => OrganizationSlugMiddleware::class,
                'role' => RoleMiddleware::class,
                'permission' => PermissionMiddleware::class,
                'role_or_permission' => RoleOrPermissionMiddleware::class, ],
        );
        $middleware->priority(
            [
                GlobalOrganizationMiddleware::class,
                OrganizationSlugMiddleware::class,
                RoleMiddleware::class,
                PermissionMiddleware::class,
                RoleOrPermissionMiddleware::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
