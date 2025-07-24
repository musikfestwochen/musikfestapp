<?php

use App\Models\Organization;
use App\Models\Peoplecount\Event;
use App\Services\Peoplecount\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(EventService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new EventService;
});

describe('getEvents', function () {
    it('returns events', function () {
        $org = Organization::factory()->create();
        Event::factory()->count(5)->create([
            'organization_id' => $org->id,
        ]);
        setPermissionsOrgId($org->id);

        $result = $this->service->getEvents();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(5);
    });

    it('filters events by organization', function () {
        $org = Organization::factory()->create();
        Event::factory()->count(3)->create([
            'organization_id' => $org->id,
        ]);
        setPermissionsOrgId($org->id);

        $foreignOrg = Organization::factory()->create();
        Event::factory()->count(7)->create([
            'organization_id' => $foreignOrg->id,
        ]);

        $result = $this->service->getEvents();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(3);
    });

    it('returns empty collection when no events exist', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $result = $this->service->getEvents();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('filters events by organization when no events', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $foreignOrg = Organization::factory()->create();
        Event::factory()->count(5)->create([
            'organization_id' => $foreignOrg->id,
        ]);

        $result = $this->service->getEvents();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });
});

describe('create', function () {
    it('creates an event', function () {
        $org = Organization::factory()->create();
        $attributes = [
            'organization_id' => $org->id,
            'name' => 'Test Event',
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
        ];

        $event = $this->service->create($attributes);

        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->organization_id)->toBe($org->id)
            ->and($event->name)->toBe('Test Event')
            ->and($event->starts_at)->not->toBeNull()
            ->and($event->ends_at)->not->toBeNull();

        // Verify it was saved to the database
        $this->assertDatabaseHas('peoplecount_events', [
            'id' => $event->id,
            'name' => 'Test Event',
            'organization_id' => $org->id,
        ]);
    });
});

describe('update', function () {
    it('updates an existing event', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Original Name',
        ]);

        $updatedEvent = $this->service->update($event, [
            'name' => 'Updated Name',
        ]);

        expect($updatedEvent)->toBeInstanceOf(Event::class)
            ->and($updatedEvent->id)->toBe($event->id)
            ->and($updatedEvent->name)->toBe('Updated Name');

        // Verify it was updated in the database
        $this->assertDatabaseHas('peoplecount_events', [
            'id' => $event->id,
            'name' => 'Updated Name',
        ]);
    });

    it('updates event dates', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
        ]);

        $newStartsAt = now()->addDay();
        $newEndsAt = now()->addDays(3);

        $updatedEvent = $this->service->update($event, [
            'starts_at' => $newStartsAt,
            'ends_at' => $newEndsAt,
        ]);

        expect($updatedEvent)->toBeInstanceOf(Event::class)
            ->and($updatedEvent->starts_at->toDateTimeString())->toBe($newStartsAt->toDateTimeString())
            ->and($updatedEvent->ends_at->toDateTimeString())->toBe($newEndsAt->toDateTimeString());
    });
});
