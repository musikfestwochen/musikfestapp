<?php

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('archives a sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create();

    $this->actingAs($admin)
        ->post(route('peoplecount.sensors.archive.store', [
            'organization' => $organization->slug,
            'sensor' => $sensor->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.index', ['organization' => $organization->slug]));

    expect($sensor->refresh()->archived_at)->not->toBeNull();
});

it('restores an archived sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create(['archived_at' => now()]);

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.archive.destroy', [
            'organization' => $organization->slug,
            'sensor' => $sensor->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.index', [
            'organization' => $organization->slug,
            'archived' => true,
        ]));

    expect($sensor->refresh()->archived_at)->toBeNull();
});
