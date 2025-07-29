<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use App\Services\Peoplecount\AreaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(AreaService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new AreaService;
});

describe('getAreas', function () {
    it('returns areas', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        Area::factory()->count(5)->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $result = $this->service->getAreas();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(5);

        // Verify that relationships are loaded
        $result->each(function ($area) {
            expect($area->relationLoaded('event'))->toBeTrue()
                ->and($area->relationLoaded('assignments'))->toBeTrue();
        });
    });

    it('filters areas by organization through events', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        Area::factory()->count(3)->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $foreignOrg = Organization::factory()->create();
        $foreignEvent = Event::factory()->create([
            'organization_id' => $foreignOrg->id,
        ]);
        Area::factory()->count(7)->create([
            'event_id' => $foreignEvent->id,
        ]);

        $result = $this->service->getAreas();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(3);

        // Verify that relationships are loaded
        $result->each(function ($area) {
            expect($area->relationLoaded('event'))->toBeTrue()
                ->and($area->relationLoaded('assignments'))->toBeTrue();
        });
    });

    it('returns empty collection when no areas exist', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $result = $this->service->getAreas();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('returns all areas when global organization', function () {
        $org1 = Organization::factory()->create();
        $event1 = Event::factory()->create([
            'organization_id' => $org1->id,
        ]);
        Area::factory()->count(3)->create([
            'event_id' => $event1->id,
        ]);

        $org2 = Organization::factory()->create();
        $event2 = Event::factory()->create([
            'organization_id' => $org2->id,
        ]);
        Area::factory()->count(4)->create([
            'event_id' => $event2->id,
        ]);

        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getAreas();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(7);

        // Verify that relationships are loaded
        $result->each(function ($area) {
            expect($area->relationLoaded('event'))->toBeTrue()
                ->and($area->relationLoaded('assignments'))->toBeTrue();
        });
    });
});

describe('create', function () {
    it('creates an area', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        setPermissionsOrgId($org->id);

        $attributes = [
            'name' => 'Test Area',
            'event_id' => $event->id,
        ];

        $area = $this->service->create($attributes);

        expect($area)->toBeInstanceOf(Area::class)
            ->and($area->event_id)->toBe($event->id)
            ->and($area->name)->toBe('Test Area');

        // Verify it was saved to the database
        $this->assertDatabaseHas('peoplecount_areas', [
            'id' => $area->id,
            'name' => 'Test Area',
            'event_id' => $event->id,
        ]);
    });

    it('throws authorization exception when event belongs to different organization', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $foreignOrg = Organization::factory()->create();
        $foreignEvent = Event::factory()->create([
            'organization_id' => $foreignOrg->id,
        ]);

        $attributes = [
            'name' => 'Test Area',
            'event_id' => $foreignEvent->id,
        ];

        expect(fn () => $this->service->create($attributes))
            ->toThrow(AuthorizationException::class);
    });

    it('allows creating area for any event when global organization', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);

        $attributes = [
            'name' => 'Test Area',
            'event_id' => $event->id,
        ];

        $area = $this->service->create($attributes);

        expect($area)->toBeInstanceOf(Area::class)
            ->and($area->event_id)->toBe($event->id)
            ->and($area->name)->toBe('Test Area');
    });
});

describe('update', function () {
    it('updates an existing area', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Original Name',
        ]);
        setPermissionsOrgId($org->id);

        $updatedArea = $this->service->update($area, [
            'name' => 'Updated Name',
            'event_id' => $event->id,
        ]);

        expect($updatedArea)->toBeInstanceOf(Area::class)
            ->and($updatedArea->id)->toBe($area->id)
            ->and($updatedArea->name)->toBe('Updated Name');

        // Verify it was updated in the database
        $this->assertDatabaseHas('peoplecount_areas', [
            'id' => $area->id,
            'name' => 'Updated Name',
        ]);
    });

    it('updates area event', function () {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $event2 = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event1->id,
        ]);
        setPermissionsOrgId($org->id);

        $updatedArea = $this->service->update($area, [
            'name' => $area->name,
            'event_id' => $event2->id,
        ]);

        expect($updatedArea)->toBeInstanceOf(Area::class)
            ->and($updatedArea->event_id)->toBe($event2->id);

        // Verify it was updated in the database
        $this->assertDatabaseHas('peoplecount_areas', [
            'id' => $area->id,
            'event_id' => $event2->id,
        ]);
    });

    it('throws authorization exception when event belongs to different organization', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $foreignOrg = Organization::factory()->create();
        $foreignEvent = Event::factory()->create([
            'organization_id' => $foreignOrg->id,
        ]);

        expect(fn () => $this->service->update($area, [
            'name' => 'Updated Name',
            'event_id' => $foreignEvent->id,
        ]))->toThrow(AuthorizationException::class);
    });

    it('allows updating area to any event when global organization', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $foreignOrg = Organization::factory()->create();
        $foreignEvent = Event::factory()->create([
            'organization_id' => $foreignOrg->id,
        ]);

        setPermissionsOrgId(GLOBAL_ORG_ID);

        $updatedArea = $this->service->update($area, [
            'name' => 'Updated Name',
            'event_id' => $foreignEvent->id,
        ]);

        expect($updatedArea)->toBeInstanceOf(Area::class)
            ->and($updatedArea->event_id)->toBe($foreignEvent->id);
    });
});

describe('getWithRelations', function () {
    it('returns area with loaded relationships', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $result = $this->service->getWithRelations($area);

        dump($result);

        expect($result)->toBeInstanceOf(Area::class)
            ->and($result->id)->toBe($area->id)
            ->and($result->relationLoaded('event'))->toBeTrue()
            ->and($result->relationLoaded('assignments'))->toBeTrue()
            ->and($result->relationLoaded('areaSingleResets'))->toBeTrue();
    });

    it('loads nested sensor relationships on assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $result = $this->service->getWithRelations($area);

        expect($result)->toBeInstanceOf(Area::class)
            ->and($result->relationLoaded('assignments'))->toBeTrue();

        // If there are assignments, they should have sensor relationship loaded
        if ($result->assignments->isNotEmpty()) {
            expect($result->assignments->first()->relationLoaded('sensor'))->toBeTrue();
        }
    });

    it('loads nested user relationships on area single resets', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);
        setPermissionsOrgId($org->id);

        $result = $this->service->getWithRelations($area);

        expect($result)->toBeInstanceOf(Area::class)
            ->and($result->relationLoaded('areaSingleResets'))->toBeTrue();

        // If there are area single resets, they should have user relationship loaded
        if ($result->areaSingleResets->isNotEmpty()) {
            expect($result->areaSingleResets->first()->relationLoaded('createdBy'))->toBeTrue();
        }
    });
});
