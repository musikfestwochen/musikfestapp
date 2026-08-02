<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('stores a sensor share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();

    $this->actingAs($admin)
        ->post(route('peoplecount.sensors.shares.store', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
        ]), [
            'borrower_organization_id' => (string) $borrower->id,
            'starts_at' => '2026-08-01T09:00:00.000Z',
            'ends_at' => '2026-08-01T18:00:00.000Z',
        ])
        ->assertRedirect(route('peoplecount.sensors.edit', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
        ]));

    $this->assertDatabaseHas('peoplecount_sensor_shares', [
        'sensor_id' => $sensor->id,
        'owner_organization_id' => $owner->id,
        'borrower_organization_id' => $borrower->id,
    ]);
});

it('returns validation errors when storing invalid share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();

    $this->actingAs($admin)
        ->from(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->post(route('peoplecount.sensors.shares.store', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
        ]), [
            'borrower_organization_id' => $owner->id,
            'starts_at' => '2026-08-01T09:00:00.000Z',
            'ends_at' => '2026-08-01T18:00:00.000Z',
        ])
        ->assertRedirect(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->assertSessionHasErrors('borrower_organization_id');
});

it('updates a sensor share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $newBorrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->put(route('peoplecount.sensors.shares.update', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]), [
            'borrower_organization_id' => (string) $newBorrower->id,
            'starts_at' => '2026-08-01T08:00:00.000Z',
            'ends_at' => '2026-08-01T19:00:00.000Z',
        ])
        ->assertRedirect(route('peoplecount.sensors.edit', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
        ]));

    expect($share->refresh()->borrower_organization_id)->toBe($newBorrower->id);
});

it('rejects updating a share for another sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $otherSensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($otherSensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->put(route('peoplecount.sensors.shares.update', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]), [
            'borrower_organization_id' => $borrower->id,
            'starts_at' => '2026-08-01T08:00:00.000Z',
            'ends_at' => '2026-08-01T19:00:00.000Z',
        ])
        ->assertNotFound();
});

it('returns validation errors when updating invalid share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->from(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->put(route('peoplecount.sensors.shares.update', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]), [
            'borrower_organization_id' => $owner->id,
            'starts_at' => '2026-08-01T08:00:00.000Z',
            'ends_at' => '2026-08-01T19:00:00.000Z',
        ])
        ->assertRedirect(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->assertSessionHasErrors('borrower_organization_id');
});

it('destroys a sensor share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.shares.destroy', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.edit', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
        ]));

    $this->assertDatabaseMissing('peoplecount_sensor_shares', ['id' => $share->id]);
});

it('rejects destroying a share for another sensor', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $otherSensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($otherSensor)->withBorrowerOrganization($borrower)->create();

    $this->actingAs($admin)
        ->delete(route('peoplecount.sensors.shares.destroy', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]))
        ->assertNotFound();
});

it('returns validation errors when destroying used share', function () {
    $admin = User::factory()->globalAdmin()->create();
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();
    $event = Event::factory()->withOrganization($borrower)->create();
    $area = Area::factory()->withEvent($event)->create();
    Assignment::factory()->withEvent($event)->withArea($area)->withSensor($sensor)->create([
        'sensor_share_id' => $share->id,
    ]);

    $this->actingAs($admin)
        ->from(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->delete(route('peoplecount.sensors.shares.destroy', [
            'organization' => $owner->slug,
            'sensor' => $sensor->id,
            'share' => $share->id,
        ]))
        ->assertRedirect(route('peoplecount.sensors.edit', ['organization' => $owner->slug, 'sensor' => $sensor->id]))
        ->assertSessionHasErrors('sensor_share_id');
});
