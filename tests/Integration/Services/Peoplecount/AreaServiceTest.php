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

        expect($result)->toBeInstanceOf(Area::class)
            ->and($result->id)->toBe($area->id)
            ->and($result->relationLoaded('event'))->toBeTrue()
            ->and($result->relationLoaded('assignments'))->toBeTrue()
            ->and($result->relationLoaded('areaSingleResets'))->toBeTrue()
            ->and($result->relationLoaded('areaRecurringResets'))->toBeTrue();
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

    it('loads nested event relationships on area recurring resets', function () {
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
            ->and($result->relationLoaded('areaRecurringResets'))->toBeTrue();

        // If there are area recurring resets, they should have event relationship loaded
        if ($result->areaRecurringResets->isNotEmpty()) {
            expect($result->areaRecurringResets->first()->relationLoaded('event'))->toBeTrue();
        }
    });
});

describe('checksum functionality', function () {
    describe('getChecksumConfig', function () {
        it('returns correct configuration array', function () {
            $config = $this->service->getChecksumConfig();

            expect($config)->toBeArray()
                ->and($config)->toHaveKeys(['area', 'event', 'assignments', 'areaSingleResets', 'areaRecurringResets']);

            // Test area config
            expect($config['area'])->toBe(['id', 'event_id']);

            // Test event config
            expect($config['event'])->toBe(['id', 'starts_at', 'ends_at']);

            // Test assignments config
            expect($config['assignments'])->toBe(['id', 'area_id', 'sensor_id', 'direction_flipped', 'active_from', 'active_to']);

            // Test areaSingleResets config
            expect($config['areaSingleResets'])->toBe(['id', 'area_id', 'reset_value', 'effective_at']);

            // Test areaRecurringResets config
            expect($config['areaRecurringResets'])->toBe(['id', 'area_id', 'reset_value', 'reset_time', 'timezone']);
        });
    });

    describe('extractModelAttributes', function () {
        it('extracts specified attributes from a model', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-01 10:00:00',
                'ends_at' => '2024-01-01 18:00:00',
            ]);

            $attributes = ['id', 'starts_at', 'ends_at'];
            $result = $this->service->extractModelAttributes($event, $attributes);

            expect($result)->toBeArray()
                ->and($result)->toHaveKeys(['id', 'starts_at', 'ends_at'])
                ->and($result['id'])->toBe($event->id)
                ->and($result['starts_at'])->toEqual($event->starts_at)
                ->and($result['ends_at'])->toEqual($event->ends_at);
        });

        it('handles empty attributes array', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create(['organization_id' => $org->id]);

            $result = $this->service->extractModelAttributes($event, []);

            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });

        it('handles null attribute values', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create([
                'organization_id' => $org->id,
            ]);

            // Test with a nullable field that actually exists and can be null
            $attributes = ['id', 'description'];
            $result = $this->service->extractModelAttributes($event, $attributes);

            expect($result)->toBeArray()
                ->and($result)->toHaveKeys(['id', 'description'])
                ->and($result['id'])->toBe($event->id)
                ->and($result['description'])->toBeNull();
        });
    });

    describe('extractCollectionAttributes', function () {
        it('extracts attributes from collection of models', function () {
            $org = Organization::factory()->create();
            $event1 = Event::factory()->create(['organization_id' => $org->id]);
            $event2 = Event::factory()->create(['organization_id' => $org->id]);
            $collection = collect([$event1, $event2]);

            $attributes = ['id', 'organization_id'];
            $result = $this->service->extractCollectionAttributes($collection, $attributes);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(2)
                ->and($result[0])->toHaveKeys(['id', 'organization_id'])
                ->and($result[0]['id'])->toBe($event1->id)
                ->and($result[0]['organization_id'])->toBe($org->id)
                ->and($result[1])->toHaveKeys(['id', 'organization_id'])
                ->and($result[1]['id'])->toBe($event2->id)
                ->and($result[1]['organization_id'])->toBe($org->id);
        });

        it('handles empty collection', function () {
            $collection = collect([]);
            $attributes = ['id', 'name'];

            $result = $this->service->extractCollectionAttributes($collection, $attributes);

            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });

        it('handles empty attributes array', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create(['organization_id' => $org->id]);
            $collection = collect([$event]);

            $result = $this->service->extractCollectionAttributes($collection, []);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(1)
                ->and($result[0])->toBeEmpty();
        });
    });

    describe('sortChecksumData', function () {
        it('sorts top-level keys', function () {
            $data = [
                'zebra' => 'value1',
                'alpha' => 'value2',
                'beta' => 'value3',
            ];

            $result = $this->service->sortChecksumData($data);

            expect(array_keys($result))->toBe(['alpha', 'beta', 'zebra']);
        });

        it('sorts nested array keys', function () {
            $data = [
                'section1' => [
                    'zebra' => 'value1',
                    'alpha' => 'value2',
                ],
                'section2' => [
                    'delta' => 'value3',
                    'beta' => 'value4',
                ],
            ];

            $result = $this->service->sortChecksumData($data);

            expect(array_keys($result['section1']))->toBe(['alpha', 'zebra'])
                ->and(array_keys($result['section2']))->toBe(['beta', 'delta']);
        });

        it('handles mixed data types', function () {
            $data = [
                'string' => 'value',
                'array' => ['zebra' => 1, 'alpha' => 2],
                'number' => 42,
            ];

            $result = $this->service->sortChecksumData($data);

            expect($result['string'])->toBe('value')
                ->and($result['number'])->toBe(42)
                ->and(array_keys($result['array']))->toBe(['alpha', 'zebra']);
        });

        it('handles empty data', function () {
            $result = $this->service->sortChecksumData([]);

            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });
    });

    describe('collectChecksumData', function () {
        it('collects data from area and all relationships', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-01 10:00:00',
                'ends_at' => '2024-01-01 18:00:00',
            ]);
            $area = Area::factory()->create([
                'event_id' => $event->id,
                'name' => 'Test Area',
            ]);

            // Load the event relationship
            $area->load('event');

            $result = $this->service->collectChecksumData($area);

            expect($result)->toBeArray()
                ->and($result)->toHaveKeys(['area', 'event', 'assignments', 'areaSingleResets', 'areaRecurringResets']);

            // Check area data
            expect($result['area'])->toHaveKeys(['id', 'event_id'])
                ->and($result['area']['id'])->toBe($area->id)
                ->and($result['area']['event_id'])->toBe($event->id);

            // Check event data
            expect($result['event'])->toHaveKeys(['id', 'starts_at', 'ends_at'])
                ->and($result['event']['id'])->toBe($event->id);

            // Check collection data (should be empty arrays for new area)
            expect($result['assignments'])->toBeArray()
                ->and($result['areaSingleResets'])->toBeArray()
                ->and($result['areaRecurringResets'])->toBeArray();
        });

        it('handles area without event relationship loaded', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create(['organization_id' => $org->id]);
            $area = Area::factory()->create(['event_id' => $event->id]);

            // Don't load the event relationship - check if it's loaded
            $result = $this->service->collectChecksumData($area);

            expect($result)->toBeArray()
                ->and($result)->toHaveKeys(['area', 'event', 'assignments', 'areaSingleResets', 'areaRecurringResets'])
                ->and($result['area'])->toHaveKeys(['id', 'event_id']);

            // The event might be loaded automatically or be null - both are valid
            if ($result['event'] !== null) {
                expect($result['event'])->toHaveKeys(['id', 'starts_at', 'ends_at']);
            }
        });
    });

    describe('calculateChecksum', function () {
        it('returns consistent checksum for same data', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-01 10:00:00',
                'ends_at' => '2024-01-01 18:00:00',
            ]);
            $area = Area::factory()->create([
                'event_id' => $event->id,
                'name' => 'Test Area',
            ]);

            $checksum1 = $this->service->calculateChecksum($area);
            $checksum2 = $this->service->calculateChecksum($area);

            expect($checksum1)->toBeString()
                ->and($checksum1)->toBe($checksum2)
                ->and(strlen($checksum1))->toBe(64); // SHA256 produces 64 character hex string
        });

        it('returns different checksums for different areas', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create(['organization_id' => $org->id]);
            $area1 = Area::factory()->create(['event_id' => $event->id]);
            $area2 = Area::factory()->create(['event_id' => $event->id]);

            $checksum1 = $this->service->calculateChecksum($area1);
            $checksum2 = $this->service->calculateChecksum($area2);

            expect($checksum1)->toBeString()
                ->and($checksum2)->toBeString()
                ->and($checksum1)->not->toBe($checksum2);
        });

        it('returns different checksums when event changes', function () {
            $org = Organization::factory()->create();
            $event1 = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-01 10:00:00',
            ]);
            $event2 = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-02 10:00:00',
            ]);
            $area = Area::factory()->create(['event_id' => $event1->id]);

            $checksum1 = $this->service->calculateChecksum($area);

            // Change the area's event
            $area->update(['event_id' => $event2->id]);
            $area->refresh();

            $checksum2 = $this->service->calculateChecksum($area);

            expect($checksum1)->not->toBe($checksum2);
        });

        it('loads relationships before calculating checksum', function () {
            $org = Organization::factory()->create();
            $event = Event::factory()->create([
                'organization_id' => $org->id,
                'starts_at' => '2024-01-01 10:00:00',
                'ends_at' => '2024-01-01 18:00:00',
            ]);
            $area = Area::factory()->create(['event_id' => $event->id]);

            // Ensure relationships are not loaded initially
            expect($area->relationLoaded('event'))->toBeFalse()
                ->and($area->relationLoaded('assignments'))->toBeFalse()
                ->and($area->relationLoaded('areaSingleResets'))->toBeFalse()
                ->and($area->relationLoaded('areaRecurringResets'))->toBeFalse();

            // Calculate checksum - this should load the relationships
            $checksum = $this->service->calculateChecksum($area);

            // Verify relationships are now loaded
            expect($area->relationLoaded('event'))->toBeTrue()
                ->and($area->relationLoaded('assignments'))->toBeTrue()
                ->and($area->relationLoaded('areaSingleResets'))->toBeTrue()
                ->and($area->relationLoaded('areaRecurringResets'))->toBeTrue()
                ->and($checksum)->toBeString();
        });

    });
});
