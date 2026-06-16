<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use App\Services\Peoplecount\AssignmentService;
use App\Services\Peoplecount\SensorService;
use App\Services\Peoplecount\SensorShareService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

covers(SensorShareService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->assignmentService = new AssignmentService;
    $this->sensorShareService = new SensorShareService;
    $this->sensorService = new SensorService;
});

it('lists lent and borrowed shares with relationships', function () {
    [$owner, $borrower, $sensor, , , $share] = sharedSensorScenario();

    $lentShares = $this->sensorShareService->getLentShares($owner);
    $borrowedShares = $this->sensorShareService->getBorrowedShares($borrower);

    expect($lentShares)->toHaveCount(1)
        ->and($lentShares->first()->is($share))->toBeTrue()
        ->and($lentShares->first()->relationLoaded('sensor'))->toBeTrue()
        ->and($lentShares->first()->relationLoaded('borrowerOrganization'))->toBeTrue()
        ->and($borrowedShares)->toHaveCount(1)
        ->and($borrowedShares->first()->is($share))->toBeTrue()
        ->and($borrowedShares->first()->relationLoaded('sensor'))->toBeTrue()
        ->and($borrowedShares->first()->relationLoaded('ownerOrganization'))->toBeTrue()
        ->and($borrowedShares->first()->sensor->relationLoaded('organization'))->toBeTrue();
});

it('creates a sensor share for the current owner organization', function () {
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();

    setPermissionsOrgId($owner->id);

    $share = $this->sensorShareService->create([
        'sensor_id' => $sensor->id,
        'borrower_organization_id' => $borrower->id,
        'created_by' => null,
        'starts_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-01 18:00:00',
    ]);

    expect($share->sensor_id)->toBe($sensor->id)
        ->and($share->owner_organization_id)->toBe($owner->id)
        ->and($share->borrower_organization_id)->toBe($borrower->id);
});

it('updates a share and can change borrower before assignments use it', function () {
    [$owner, , , , , $share] = sharedSensorScenario();
    $newBorrower = Organization::factory()->create();

    setPermissionsOrgId($owner->id);

    $updated = $this->sensorShareService->update($share, [
        'borrower_organization_id' => $newBorrower->id,
        'starts_at' => '2026-08-01 08:00:00',
        'ends_at' => '2026-08-01 19:00:00',
    ]);

    expect($updated->borrower_organization_id)->toBe($newBorrower->id)
        ->and($updated->starts_at->toDateTimeString())->toBe('2026-08-01 08:00:00')
        ->and($updated->ends_at->toDateTimeString())->toBe('2026-08-01 19:00:00');
});

it('deletes an unused share', function () {
    [$owner, , , , , $share] = sharedSensorScenario();

    setPermissionsOrgId($owner->id);

    $this->sensorShareService->delete($share);

    $this->assertSoftDeleted('peoplecount_sensor_shares', ['id' => $share->id]);
});

it('allows global organization to manage shares', function () {
    [, $borrower, , , , $share] = sharedSensorScenario();

    setPermissionsOrgId(GLOBAL_ORG_ID);

    $updated = $this->sensorShareService->update($share, [
        'borrower_organization_id' => $borrower->id,
        'starts_at' => '2026-08-01 08:00:00',
        'ends_at' => '2026-08-01 19:00:00',
    ]);

    expect($updated->starts_at->toDateTimeString())->toBe('2026-08-01 08:00:00');
});

function sharedSensorScenario(): array
{
    $owner = Organization::factory()->create();
    $borrower = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();
    $event = Event::factory()->withOrganization($borrower)->create([
        'starts_at' => Carbon::parse('2026-08-01 08:00:00'),
        'ends_at' => Carbon::parse('2026-08-01 22:00:00'),
    ]);
    $area = Area::factory()->withEvent($event)->create();
    $share = SensorShare::factory()
        ->withSensor($sensor)
        ->withBorrowerOrganization($borrower)
        ->create([
            'starts_at' => Carbon::parse('2026-08-01 09:00:00'),
            'ends_at' => Carbon::parse('2026-08-01 18:00:00'),
        ]);

    return [$owner, $borrower, $sensor, $event, $area, $share];
}

it('allows borrower organization to assign a shared sensor inside the share window', function () {
    [, $borrower, $sensor, $event, $area, $share] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);

    $assignment = $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    expect($assignment->sensor_share_id)->toBe($share->id);
});

it('rejects sharing a sensor with its owning organization', function () {
    $owner = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($owner)->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);

    $this->expectException(ValidationException::class);

    $this->sensorShareService->create([
        'sensor_id' => $sensor->id,
        'borrower_organization_id' => $owner->id,
        'starts_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-01 18:00:00',
    ]);
});

it('rejects borrower assignment outside the share window', function () {
    [, $borrower, $sensor, $event, $area] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);

    $this->expectException(AuthorizationException::class);

    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 08:30:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);
});

it('rejects creating an assignment with an archived sensor', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create([
        'archived_at' => now(),
    ]);
    $event = Event::factory()->withOrganization($organization)->create([
        'starts_at' => Carbon::parse('2026-08-01 08:00:00'),
        'ends_at' => Carbon::parse('2026-08-01 22:00:00'),
    ]);
    $area = Area::factory()->withEvent($event)->create();

    setPermissionsOrgId($organization->id);

    $this->expectException(ValidationException::class);

    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);
});

it('allows updating an existing assignment that already uses an archived sensor', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create([
        'archived_at' => now(),
    ]);
    $event = Event::factory()->withOrganization($organization)->create([
        'starts_at' => Carbon::parse('2026-08-01 08:00:00'),
        'ends_at' => Carbon::parse('2026-08-01 22:00:00'),
    ]);
    $area = Area::factory()->withEvent($event)->create();
    $assignment = Assignment::factory()->withEvent($event)->withArea($area)->withSensor($sensor)->create([
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($organization->id);

    $updatedAssignment = $this->assignmentService->update($assignment, [
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => true,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    expect($updatedAssignment->direction_flipped)->toBeTrue();
});

it('allows overlapping assignments for the same sensor in different organizations', function () {
    [$owner, $borrower, $sensor, $borrowerEvent, $borrowerArea] = sharedSensorScenario();

    $ownerEvent = Event::factory()->withOrganization($owner)->create([
        'starts_at' => Carbon::parse('2026-08-01 08:00:00'),
        'ends_at' => Carbon::parse('2026-08-01 22:00:00'),
    ]);
    $ownerArea = Area::factory()->withEvent($ownerEvent)->create();

    Assignment::factory()->withEvent($ownerEvent)->withArea($ownerArea)->withSensor($sensor)->create([
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($borrower->id);

    $assignment = $this->assignmentService->create([
        'event_id' => $borrowerEvent->id,
        'area_id' => $borrowerArea->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:30:00',
        'active_to' => '2026-08-01 11:30:00',
    ]);

    expect($assignment)->toBeInstanceOf(Assignment::class);
});

it('allows adjacent assignments in the same organization', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->withOrganization($organization)->create();
    $event = Event::factory()->withOrganization($organization)->create([
        'starts_at' => Carbon::parse('2026-08-01 08:00:00'),
        'ends_at' => Carbon::parse('2026-08-01 22:00:00'),
    ]);
    $area = Area::factory()->withEvent($event)->create();

    Assignment::factory()->withEvent($event)->withArea($area)->withSensor($sensor)->create([
        'direction_flipped' => false,
        'active_from' => '2026-08-01 09:00:00',
        'active_to' => '2026-08-01 10:00:00',
    ]);

    setPermissionsOrgId($organization->id);

    $assignment = $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 11:00:00',
    ]);

    expect($assignment)->toBeInstanceOf(Assignment::class);
});

it('blocks deleting a share that is used by an assignment', function () {
    [$owner, $borrower, $sensor, $event, $area, $share] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);
    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($owner->id);

    $this->expectException(ValidationException::class);

    $this->sensorShareService->delete($share);
});

it('database rejects hard deleting a share that is used by an assignment', function () {
    [, $borrower, $sensor, $event, $area, $share] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);
    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    $this->expectException(QueryException::class);

    $share->forceDelete();
});

it('blocks shrinking share period outside assignments using the share', function () {
    [$owner, $borrower, $sensor, $event, $area, $share] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);
    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($owner->id);

    $this->expectException(ValidationException::class);

    $this->sensorShareService->update($share, [
        'borrower_organization_id' => $borrower->id,
        'starts_at' => '2026-08-01 11:00:00',
        'ends_at' => '2026-08-01 18:00:00',
    ]);
});

it('blocks changing borrower organization while assignments use the share', function () {
    [$owner, $borrower, $sensor, $event, $area, $share] = sharedSensorScenario();
    $anotherBorrower = Organization::factory()->create();

    setPermissionsOrgId($borrower->id);
    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($owner->id);

    $this->expectException(ValidationException::class);

    $this->sensorShareService->update($share, [
        'borrower_organization_id' => $anotherBorrower->id,
        'starts_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-01 18:00:00',
    ]);
});

it('blocks sensor deletion when any assignment references the sensor', function () {
    [$owner, $borrower, $sensor, $event, $area] = sharedSensorScenario();

    setPermissionsOrgId($borrower->id);
    $this->assignmentService->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'direction_flipped' => false,
        'active_from' => '2026-08-01 10:00:00',
        'active_to' => '2026-08-01 12:00:00',
    ]);

    setPermissionsOrgId($owner->id);

    $this->expectException(ValidationException::class);

    $this->sensorService->delete($sensor);
});

it('hides archived sensors from default sensor list', function () {
    $organization = Organization::factory()->create();
    $activeSensor = Sensor::factory()->withOrganization($organization)->create();
    $archivedSensor = Sensor::factory()->withOrganization($organization)->create();

    setPermissionsOrgId($organization->id);

    $this->sensorService->archive($archivedSensor);

    expect($this->sensorService->getSensors()->pluck('id')->all())
        ->toBe([$activeSensor->id])
        ->and($this->sensorService->getSensors(true)->pluck('id')->all())
        ->toBe([$archivedSensor->id]);
});
