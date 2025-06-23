<?php

use App\Http\Requests\Peoplecount\SensorDestroyRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(SensorDestroyRequest::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->request = new SensorDestroyRequest;
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy sensors', function () {
    $user = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create();
    $user->organizations()->attach($organization->id);
    $orgSlug = $organization->slug;

    $response = $this->actingAs($user)->call('DELETE', route('peoplecount.sensors.destroy', [
        'organization' => $orgSlug,
        'sensor' => $sensor->id,
    ]));

    expect($response->getStatusCode())->toBe(302);
});
