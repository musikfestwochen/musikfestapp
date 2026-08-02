<?php

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

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('verifies that the Gate::before callback (Super Admin)', function () {
    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    $user->assignRole('SuperAdmin');

    $result = Gate::forUser($user)->allows('anything'); // 'anything' triggers the before hook

    expect($result)->toBeTrue();
});

it('defines a gate for laravel pulse', function () {
    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    $user->assignRole('Admin');

    $result = Gate::forUser($user)->allows('viewPulse');

    expect($result)->toBeTrue();
});

it('auto-resolves concrete services without explicit bindings', function () {
    $services = [
        GlobalPermissionService::class,
        SensorService::class,
        IntervalCountService::class,
        EventService::class,
        AreaService::class,
        AreaResetService::class,
        AssignmentService::class,
        AreaAggregationService::class,
        AlertService::class,
    ];

    foreach ($services as $service) {
        expect(app()->bound($service))->toBeFalse()
            ->and(resolve($service))->toBeInstanceOf($service);
    }
});
