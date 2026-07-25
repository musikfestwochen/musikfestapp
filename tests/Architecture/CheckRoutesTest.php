<?php

use Illuminate\Support\Facades\Artisan;

it('tests if no unwanted routes are exposed', function () {

    $shouldIgnoreRoute = static fn (string $uri): bool => str_starts_with($uri, '_debugbar')
        || str_starts_with($uri, 'livewire-')
        || str_starts_with($uri, 'livewire/');

    $normalizeRouteUri = static fn (string $uri): string => preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*):[^}]*\}/', '{$1}', $uri) ?? $uri;

    $allowedRoutes = [
        ['method' => 'GET|HEAD', 'uri' => '/'],
        ['method' => 'GET|HEAD', 'uri' => 'confirm-password'],
        ['method' => 'POST', 'uri' => 'confirm-password'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/dashboard'],
        ['method' => 'PATCH', 'uri' => 'admin/peoplecount-aggregations'],
        ['method' => 'DELETE', 'uri' => 'admin/peoplecount-aggregations'],
        ['method' => 'POST', 'uri' => 'email/verification-notification'],
        ['method' => 'GET|HEAD', 'uri' => 'forgot-password'],
        ['method' => 'POST', 'uri' => 'forgot-password'],
        ['method' => 'GET|HEAD', 'uri' => 'login'],
        ['method' => 'POST', 'uri' => 'login'],
        ['method' => 'POST', 'uri' => 'logout'],
        ['method' => 'POST', 'uri' => 'reset-password'],
        ['method' => 'GET|HEAD', 'uri' => 'reset-password/{token}'],
        ['method' => 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS', 'uri' => 'settings'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/appearance'],
        ['method' => 'PATCH', 'uri' => 'settings/appearance'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/password'],
        ['method' => 'PUT', 'uri' => 'settings/password'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/profile'],
        ['method' => 'PATCH', 'uri' => 'settings/profile'],
        ['method' => 'DELETE', 'uri' => 'settings/profile'],
        ['method' => 'GET|HEAD', 'uri' => 'storage/{path}'],
        ['method' => 'PUT', 'uri' => 'storage/{path}'],
        ['method' => 'GET|HEAD', 'uri' => 'up'],
        ['method' => 'GET|HEAD', 'uri' => 'verify-email'],
        ['method' => 'GET|HEAD', 'uri' => 'verify-email/{id}/{hash}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/users'],
        ['method' => 'POST', 'uri' => 'admin/users'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/users/create'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/users/{user}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/users/{user}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => 'admin/users/{user}'],
        ['method' => 'DELETE', 'uri' => 'admin/users/{user}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/organizations'],
        ['method' => 'POST', 'uri' => 'admin/organizations'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/organizations/create'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/organizations/{organization}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/organizations/{organization}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => 'admin/organizations/{organization}'],
        ['method' => 'DELETE', 'uri' => 'admin/organizations/{organization}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/dashboard'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/users'],
        ['method' => 'POST', 'uri' => '{organization}/users'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/users/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/users/{user}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/users/{user}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/users/{user}'],
        ['method' => 'DELETE', 'uri' => '{organization}/users/{user}'],
        ['method' => 'GET|HEAD', 'uri' => 'start'],
        ['method' => 'POST', 'uri' => 'organization/select'],

        // People Count Sensor Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/sensors'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/sensors'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/sensors/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/sensors/{sensor}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/sensors/{sensor}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/sensors/{sensor}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/sensors/{sensor}'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/sensors/{sensor}/regenerate-token'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/sensors/{sensor}/archive'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/sensors/{sensor}/archive'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/sensors/{sensor}/shares'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/sensors/{sensor}/shares/{share}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/sensors/{sensor}/shares/{share}'],

        // Stage Safety Sensor Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/stage-safety/sensors'],
        ['method' => 'POST', 'uri' => '{organization}/stage-safety/sensors'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/stage-safety/sensors/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}'],
        ['method' => 'DELETE', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}'],
        ['method' => 'POST', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}/regenerate-token'],
        ['method' => 'DELETE', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}/revoke-token'],
        ['method' => 'POST', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}/archive'],
        ['method' => 'DELETE', 'uri' => '{organization}/stage-safety/sensors/{stageSafetySensor}/archive'],

        // People Count Events Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/events'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/events'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/events/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/events/{event}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/events/{event}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/events/{event}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/events/{event}'],

        // People Count Areas Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/areas'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/areas/{area}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/areas/{area}'],

        // People Count Alerts Routes
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/areas/{area}/alerts'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/alerts/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/alerts/{alert}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/alerts/{alert}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/areas/{area}/alerts/{alert}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/areas/{area}/alerts/{alert}'],

        // People Count Area Single Reset Routes
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/areas/{area}/single-resets'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/single-resets/create'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/areas/{area}/single-resets/{single_reset}'],

        // People Count Area Recurring Reset Routes
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets/{recurring_reset}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets/{recurring_reset}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets/{recurring_reset}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/areas/{area}/recurring-resets/{recurring_reset}'],

        // People Count Area Aggregation Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/area-aggregation'],

        // People Count Area History Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/area-count-history'],

        // People Count Sensor Health Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/sensor-health'],

        // People Count Most Active Sensors Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/most-active-sensors'],

        // People Count Assignments Routes
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/assignments'],
        ['method' => 'POST', 'uri' => '{organization}/peoplecount/assignments'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/assignments/create'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/assignments/{assignment}'],
        ['method' => 'GET|HEAD', 'uri' => '{organization}/peoplecount/assignments/{assignment}/edit'],
        ['method' => 'PUT|PATCH', 'uri' => '{organization}/peoplecount/assignments/{assignment}'],
        ['method' => 'DELETE', 'uri' => '{organization}/peoplecount/assignments/{assignment}'],

        // API Routes
        ['method' => 'POST', 'uri' => 'api/peoplecount/interval-count'],
        ['method' => 'POST', 'uri' => 'api/stage-safety/readings'],
        ['method' => 'POST', 'uri' => 'api/webcron'],

        // Laravel Sanctum routes
        ['method' => 'GET|HEAD', 'uri' => 'sanctum/csrf-cookie'],

        // Laravel Pulse routes
        ['method' => 'GET|HEAD', 'uri' => 'pulse'],

        // Log Viewer routes
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/{view?}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/files'],
        ['method' => 'POST', 'uri' => 'admin/logs/api/clear-cache-all'],
        ['method' => 'POST', 'uri' => 'admin/logs/api/delete-multiple-files'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/files/{fileIdentifier}/download'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/files/{fileIdentifier}/download/request'],
        ['method' => 'POST', 'uri' => 'admin/logs/api/files/{fileIdentifier}/clear-cache'],
        ['method' => 'DELETE', 'uri' => 'admin/logs/api/files/{fileIdentifier}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/folders'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/folders/{folderIdentifier}/download'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/folders/{folderIdentifier}/download/request'],
        ['method' => 'POST', 'uri' => 'admin/logs/api/folders/{folderIdentifier}/clear-cache'],
        ['method' => 'DELETE', 'uri' => 'admin/logs/api/folders/{folderIdentifier}'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/hosts'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/logs/api/logs'],
    ];

    // Sort allowedRoutes by method and uri
    $allowedRoutes = collect($allowedRoutes)
        ->map(fn (array $route): array => [
            'method' => $route['method'],
            'uri' => $normalizeRouteUri($route['uri']),
        ])
        ->reject(fn (array $route): bool => $shouldIgnoreRoute($route['uri']))
        ->sortBy(['method', 'uri'])
        ->values()
        ->toArray();

    Artisan::call('route:list --json');
    $output = json_decode(Artisan::output(), true);

    $output = collect($output)->map(function (array $route) use ($normalizeRouteUri): array {
        return [
            'method' => $route['method'],
            'uri' => $normalizeRouteUri($route['uri']),
        ];
    })
        ->reject(fn (array $route): bool => $shouldIgnoreRoute($route['uri']))
        // Sort output by method and uri
        ->sortBy(['method', 'uri'])
        ->values();

    expect($output->toArray())->toEqualCanonicalizing($allowedRoutes)->and($output->toArray())->not()->toBeEmpty()
        ->and($output->count())->toEqual(count($output));
});
