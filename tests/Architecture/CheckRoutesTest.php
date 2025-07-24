<?php

use Illuminate\Support\Facades\Artisan;

it('tests if no unwanted routes are exposed', function () {

    $allowedRoutes = [
        ['method' => 'GET|HEAD', 'uri' => '/'],
        ['method' => 'GET|HEAD', 'uri' => 'confirm-password'],
        ['method' => 'POST', 'uri' => 'confirm-password'],
        ['method' => 'GET|HEAD', 'uri' => 'admin/dashboard'],
        ['method' => 'POST', 'uri' => 'email/verification-notification'],
        ['method' => 'GET|HEAD', 'uri' => 'forgot-password'],
        ['method' => 'POST', 'uri' => 'forgot-password'],
        ['method' => 'GET|HEAD', 'uri' => 'login'],
        ['method' => 'POST', 'uri' => 'login'],
        ['method' => 'POST', 'uri' => 'logout'],
        ['method' => 'GET|HEAD', 'uri' => 'register'],
        ['method' => 'POST', 'uri' => 'register'],
        ['method' => 'POST', 'uri' => 'reset-password'],
        ['method' => 'GET|HEAD', 'uri' => 'reset-password/{token}'],
        ['method' => 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS', 'uri' => 'settings'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/appearance'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/password'],
        ['method' => 'PUT', 'uri' => 'settings/password'],
        ['method' => 'GET|HEAD', 'uri' => 'settings/profile'],
        ['method' => 'PATCH', 'uri' => 'settings/profile'],
        ['method' => 'DELETE', 'uri' => 'settings/profile'],
        ['method' => 'GET|HEAD', 'uri' => 'storage/{path}'],
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

        // API Routes
        ['method' => 'POST', 'uri' => 'api/peoplecount/interval-count'],

        // Laravel Sanctum routes
        ['method' => 'GET|HEAD', 'uri' => 'sanctum/csrf-cookie'],

        // Debugbar routes
        ['method' => 'GET|HEAD', 'uri' => '_debugbar/assets/javascript'],
        ['method' => 'GET|HEAD', 'uri' => '_debugbar/assets/stylesheets'],
        ['method' => 'DELETE', 'uri' => '_debugbar/cache/{key}/{tags?}'],
        ['method' => 'GET|HEAD', 'uri' => '_debugbar/clockwork/{id}'],
        ['method' => 'GET|HEAD', 'uri' => '_debugbar/open'],
        ['method' => 'POST', 'uri' => '_debugbar/queries/explain'],
    ];

    // Sort allowedRoutes by method and uri
    $allowedRoutes = collect($allowedRoutes)
        ->sortBy(['method', 'uri'])
        ->values()
        ->toArray();

    Artisan::call('route:list --json');
    $output = json_decode(Artisan::output(), true);

    $output = collect($output)->map(function (array $route): array {
        return [
            'method' => $route['method'],
            'uri' => $route['uri'],
        ];
    })
        // Sort output by method and uri
        ->sortBy(['method', 'uri'])
        ->values();

    expect($output->toArray())->toEqualCanonicalizing($allowedRoutes)->and($output->toArray())->not()->toBeEmpty()
        ->and($output->count())->toEqual(count($output));
});
