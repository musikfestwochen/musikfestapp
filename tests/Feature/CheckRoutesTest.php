<?php

use Illuminate\Support\Facades\Artisan;

it('tests if no unwanted routes are exposed', function () {

    $allowedRoutes = [
        ['method' => 'GET|HEAD', 'uri' => '/'],
        ['method' => 'GET|HEAD', 'uri' => 'confirm-password'],
        ['method' => 'POST', 'uri' => 'confirm-password'],
        ['method' => 'GET|HEAD', 'uri' => 'dashboard'],
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
        ['method' => 'GET|HEAD', 'uri' => 'users'],
    ];

    Artisan::call('route:list --json');
    $output = json_decode(Artisan::output(), true);

    $output = collect($output)->map(function ($route) {
        return [
            'method' => $route['method'],
            'uri' => $route['uri'],
        ];
    });

    expect($output->toArray())->toEqualCanonicalizing($allowedRoutes);
});
