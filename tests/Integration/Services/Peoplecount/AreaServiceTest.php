<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use App\Services\Peoplecount\AreaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(AreaService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new AreaService;
});

describe('deduplicateResets', function () {
    it('throws when no valid reset type is present in a group', function () {
        $service = new AreaService;
        $resets = collect([
            [
                'at' => Carbon::parse('2025-01-01 00:00:00'),
                'reset_value' => 0,
                'type' => 'unknown_type',
            ],
        ]);

        $method = new ReflectionMethod(AreaService::class, 'deduplicateResets');

        expect(fn (): mixed => $method->invoke($service, $resets))
            ->toThrow(RuntimeException::class, 'No valid reset type found in group.');
    });
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

describe('loadAllResets', function () {
    it('loads all reset relationships', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Ensure relationships are not loaded initially
        expect($area->relationLoaded('areaSingleResets'))->toBeFalse()
            ->and($area->relationLoaded('areaRecurringResets'))->toBeFalse()
            ->and($area->relationLoaded('event'))->toBeFalse();

        $this->service->loadAllResets($area);

        // Verify relationships are now loaded
        expect($area->relationLoaded('areaSingleResets'))->toBeTrue()
            ->and($area->relationLoaded('areaRecurringResets'))->toBeTrue()
            ->and($area->relationLoaded('event'))->toBeTrue();
    });

    it('loads relationships even when they are empty', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $this->service->loadAllResets($area);

        expect($area->relationLoaded('areaSingleResets'))->toBeTrue()
            ->and($area->relationLoaded('areaRecurringResets'))->toBeTrue()
            ->and($area->relationLoaded('event'))->toBeTrue()
            ->and($area->areaSingleResets)->toBeEmpty()
            ->and($area->areaRecurringResets)->toBeEmpty();
    });
});

describe('getAreaResets', function () {
    it('returns event start reset when no other resets exist', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets)->toHaveCount(1)
            ->and($resets->first()['type'])->toBe('event_start')
            ->and($resets->first()['reset_value'])->toBe(0)
            ->and($resets->first()['at']->toDateTimeString())->toBe('2024-01-01 10:00:00');
    });

    it('includes single resets within event period', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create single reset within event period
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 14:00:00',
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets)->toHaveCount(2);

        $eventStartReset = $resets->firstWhere('type', 'event_start');
        $singleReset = $resets->firstWhere('type', 'single_reset');

        expect($eventStartReset)->not->toBeNull()
            ->and($singleReset)->not->toBeNull()
            ->and($singleReset['reset_value'])->toBe(50)
            ->and($singleReset['at']->toDateTimeString())->toBe('2024-01-01 14:00:00');
    });

    it('excludes single resets outside event period', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create single reset outside event period
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 20:00:00', // After event ends
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets)->toHaveCount(1)
            ->and($resets->first()['type'])->toBe('event_start');
    });

    it('includes recurring resets within event period', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-03 18:00:00', // 3-day event
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create recurring reset that should occur daily at 12:00
        $area->areaRecurringResets()->create([
            'reset_value' => 100,
            'reset_time' => '12:00',
            'timezone' => 'UTC',
        ]);

        $resets = $this->service->getAreaResets($area);

        // Should have event start + recurring resets
        expect($resets->count())->toBeGreaterThan(1);

        $recurringResets = $resets->where('type', 'recurring_reset');
        expect($recurringResets)->not->toBeEmpty();

        $recurringResets->each(function (array $reset) {
            expect($reset['reset_value'])->toBe(100)
                ->and($reset['type'])->toBe('recurring_reset');
        });
    });

    it('deduplicates resets at same time with correct priority', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create single reset at same time as event start
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 75,
            'effective_at' => '2024-01-01 10:00:00', // Same as event start
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        // Should only have one reset (single reset should take priority)
        expect($resets)->toHaveCount(1)
            ->and($resets->first()['type'])->toBe('single_reset')
            ->and($resets->first()['reset_value'])->toBe(75);
    });

    it('sorts resets by time', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create multiple single resets
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 30,
            'effective_at' => '2024-01-01 16:00:00',
            'created_by' => $user->id,
        ]);
        $area->areaSingleResets()->create([
            'reset_value' => 20,
            'effective_at' => '2024-01-01 12:00:00',
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets)->toHaveCount(3);

        // Check that resets are sorted by time
        $times = $resets->pluck('at')->map(fn ($time) => $time->toDateTimeString())->toArray();
        expect($times)->toBe([
            '2024-01-01 10:00:00', // Event start
            '2024-01-01 12:00:00', // First single reset
            '2024-01-01 16:00:00', // Second single reset
        ]);
    });

    it('includes at key in recurring reset array structure', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-02 18:00:00', // 2-day event
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create recurring reset
        $area->areaRecurringResets()->create([
            'reset_value' => 100,
            'reset_time' => '12:00',
            'timezone' => 'UTC',
        ]);

        $resets = $this->service->getAreaResets($area);

        $recurringResets = $resets->where('type', 'recurring_reset');
        expect($recurringResets)->not->toBeEmpty();

        // Verify each recurring reset has all required keys including 'at'
        $recurringResets->each(function (array $reset) {
            expect($reset)->toHaveKeys(['at', 'reset_value', 'type'])
                ->and($reset['at'])->toBeInstanceOf(Carbon::class)
                ->and($reset['reset_value'])->toBe(100)
                ->and($reset['type'])->toBe('recurring_reset');
        });
    });

    it('prefers event_start over recurring reset when both occur at event start', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-02 10:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create recurring reset exactly at event start
        $area->areaRecurringResets()->create([
            'reset_value' => 999,
            'reset_time' => '10:00',
            'timezone' => 'UTC',
        ]);

        $resets = $this->service->getAreaResets($area);

        // The entry at the event start timestamp should be the event_start (reset_value 0), not the recurring reset
        $atStart = $resets->firstWhere('at', Carbon::parse('2024-01-01 10:00:00'));
        expect($atStart)->not->toBeNull()
            ->and($atStart['type'])->toBe('event_start')
            ->and($atStart['reset_value'])->toBe(0);

        // And there should also be another recurring reset on the next day at 10:00
        $nextDay = Carbon::parse('2024-01-02 10:00:00');
        $atNextDay = $resets->firstWhere('at', $nextDay);
        expect($atNextDay)->not->toBeNull()
            ->and($atNextDay['type'])->toBe('recurring_reset')
            ->and($atNextDay['reset_value'])->toBe(999);
    });

    it('handles reset time exactly at event end boundary', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create single reset exactly at event end time
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 18:00:00', // Exactly at event end
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        // Should include the reset at event end time (boundary inclusive)
        expect($resets)->toHaveCount(2);
        $endTimeReset = $resets->firstWhere('reset_value', 50);
        expect($endTimeReset)->not->toBeNull()
            ->and($endTimeReset['at']->toDateTimeString())->toBe('2024-01-01 18:00:00');
    });
});

describe('calculateAndStoreAggregatedCount', function () {
    it('calculates and stores aggregated count with no assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 100;
        $checksum = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        expect($result)->toBe(100); // No change since no assignments

        // Verify it was stored in database
        $this->assertDatabaseHas('peoplecount_area_aggregated_counts', [
            'area_id' => $area->id,
            'period_start' => $start,
            'period_end' => $end,
            'count' => 100,
        ]);
    });

    it('calculates count with single assignment and interval counts', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create sensor and assignment
        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => false,
        ]);

        // Create interval counts
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 10:30:00',
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:30:00',
            'count_in' => 8,
            'count_out' => 3,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 50;
        $checksum = 'b1c2d3e4f5a6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Net count: (10-5) + (8-3) = 5 + 5 = 10
        // Final count: 50 + 10 = 60
        expect($result)->toBe(60);

        $this->assertDatabaseHas('peoplecount_area_aggregated_counts', [
            'area_id' => $area->id,
            'count' => 60,
        ]);
    });

    it('handles direction flipped assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => true, // Flipped direction
        ]);

        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 10:30:00',
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 50;
        $checksum = 'c1d2e3f4a5b6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Net count: (10-5) = 5, but flipped so -5
        // Final count: 50 + (-5) = 45
        expect($result)->toBe(45);
    });

    it('filters assignments by active period', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();

        // Assignment that's not active during our calculation period
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 14:00:00', // Starts after our end time
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => false,
        ]);

        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 10:30:00',
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 50;
        $checksum = 'd1e2f3a4b5c6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Should be unchanged since assignment is not active during calculation period
        expect($result)->toBe(50);
    });

    it('filters interval counts by assignment active period', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 11:00:00', // Assignment starts at 11:00
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => false,
        ]);

        // Interval count before assignment becomes active
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 10:30:00', // Before assignment active_from
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        // Interval count after assignment becomes active
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:30:00', // After assignment active_from
            'ts_to' => '2024-01-01 12:00:00',
            'count_in' => 8,
            'count_out' => 3,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 13:00:00');
        $startValue = 50;
        $checksum = 'e1f2a3b4c5d6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Only the second interval count should be included: (8-3) = 5
        // Final count: 50 + 5 = 55
        expect($result)->toBe(55);
    });

    it('handles multiple assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create two sensors and assignments
        $sensor1 = Sensor::factory()->create();
        $sensor2 = Sensor::factory()->create();

        $assignment1 = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor1->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => false,
        ]);

        $assignment2 = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor2->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
            'direction_flipped' => false,
        ]);

        // Create interval counts for first sensor
        IntervalCount::factory()->create([
            'sensor_id' => $sensor1->id,
            'ts_from' => '2024-01-01 10:30:00',
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        // Create interval counts for second sensor
        IntervalCount::factory()->create([
            'sensor_id' => $sensor2->id,
            'ts_from' => '2024-01-01 10:30:00',
            'ts_to' => '2024-01-01 11:00:00',
            'count_in' => 6,
            'count_out' => 2,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 50;
        $checksum = 'f1a2b3c4d5e6789012345678901234567890123456789012345678901234567890';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Sensor1: (10-5) = 5, Sensor2: (6-2) = 4, Total: 5 + 4 = 9
        // Final count: 50 + 9 = 59
        expect($result)->toBe(59);
    });

    it('updates existing aggregated count record', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 12:00:00');
        $startValue = 100;
        $checksum = '1a2b3c4d5e6f789012345678901234567890123456789012345678901234567890';

        // Create existing record
        AreaAggregatedCount::query()->create([
            'area_id' => $area->id,
            'period_start' => $start,
            'period_end' => $end,
            'count' => 75,
            'checksum' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ]);

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        expect($result)->toBe(100);

        // Verify the record was updated, not duplicated
        $this->assertDatabaseCount('peoplecount_area_aggregated_counts', 1);
        $this->assertDatabaseHas('peoplecount_area_aggregated_counts', [
            'area_id' => $area->id,
            'period_start' => $start,
            'period_end' => $end,
            'count' => 100,
        ]);
    });

    it('validates assignment active period boundary conditions', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 11:00:00', // Assignment starts at 11:00
            'active_to' => '2024-01-01 15:00:00',   // Assignment ends at 15:00
            'direction_flipped' => false,
        ]);

        // Create interval count exactly at assignment active_from boundary
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00', // Exactly at active_from
            'ts_to' => '2024-01-01 11:30:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        // Create interval count exactly at assignment active_to boundary
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 15:00:00', // Exactly at active_to
            'ts_to' => '2024-01-01 15:30:00',
            'count_in' => 8,
            'count_out' => 3,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 16:00:00');
        $startValue = 50;
        $checksum = '5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // Only the first interval should be included (ts_from >= active_from && ts_from < active_to)
        // First interval: (10-5) = 5, Second interval should be excluded (ts_from not < active_to)
        expect($result)->toBe(55);
    });

    it('excludes interval counts at assignment active_to boundary', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 11:00:00',
            'active_to' => '2024-01-01 14:00:00',
            'direction_flipped' => false,
        ]);

        // Create interval count that starts exactly at active_to (should be excluded)
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 14:00:00', // At active_to boundary
            'ts_to' => '2024-01-01 14:30:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        $start = Carbon::parse('2024-01-01 10:00:00');
        $end = Carbon::parse('2024-01-01 16:00:00');
        $startValue = 50;
        $checksum = '6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b';

        $result = $this->service->calculateAndStoreAggregatedCount($area, $start, $end, $startValue, $checksum);

        // No intervals should be included since the only interval starts at active_to
        expect($result)->toBe(50);
    });
});

describe('getLatestSingleResetBefore', function () {
    it('returns null when no resets exist', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $result = $this->service->getLatestSingleResetBefore($area);

        expect($result)->toBeNull();
    });

    it('returns null when no resets exist before specified time', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create a reset after the specified time
        $user = User::factory()->create();
        $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 14:00:00',
            'created_by' => $user->id,
        ]);

        $beforeTime = Carbon::parse('2024-01-01 10:00:00');
        $result = $this->service->getLatestSingleResetBefore($area, $beforeTime);

        expect($result)->toBeNull();
    });

    it('returns the latest reset before specified time', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $user = User::factory()->create();

        // Create multiple resets with different times
        $earlierReset = $area->areaSingleResets()->create([
            'reset_value' => 30,
            'effective_at' => '2024-01-01 10:00:00',
            'created_by' => $user->id,
        ]);

        $latestReset = $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 12:00:00',
            'created_by' => $user->id,
        ]);

        $futureReset = $area->areaSingleResets()->create([
            'reset_value' => 70,
            'effective_at' => '2024-01-01 16:00:00',
            'created_by' => $user->id,
        ]);

        $beforeTime = Carbon::parse('2024-01-01 14:00:00');
        $result = $this->service->getLatestSingleResetBefore($area, $beforeTime);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($latestReset->id)
            ->and($result->reset_value)->toBe(50)
            ->and($result->effective_at->toDateTimeString())->toBe('2024-01-01 12:00:00');
    });

    it('includes reset exactly at the specified time', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $user = User::factory()->create();

        $exactTimeReset = $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 12:00:00',
            'created_by' => $user->id,
        ]);

        $beforeTime = Carbon::parse('2024-01-01 12:00:00');
        $result = $this->service->getLatestSingleResetBefore($area, $beforeTime);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($exactTimeReset->id);
    });

    it('uses current time when no time is specified', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $user = User::factory()->create();

        // Create a reset in the past (relative to test execution time)
        $pastReset = $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => now()->subHour(),
            'created_by' => $user->id,
        ]);

        $result = $this->service->getLatestSingleResetBefore($area);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($pastReset->id);
    });
});

describe('calculateAreaCounts', function () {
    it('returns correct counts with basic interval data', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create sensor and assignment
        $sensor = Sensor::factory()->create();
        $assignment = Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval counts
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 12:00:00',
            'ts_to' => '2024-01-01 12:15:00',
            'count_in' => 15,
            'count_out' => 8,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['in', 'out', 'net', 'last_reset_type', 'last_reset_at', 'last_reset_value', 'net_plus_reset'])
            ->and($result['in'])->toBe(25)
            ->and($result['out'])->toBe(13)
            ->and($result['net'])->toBe(12)
            ->and($result['net_plus_reset'])->toBe($result['net'] + $result['last_reset_value']);
    });

    it('calculates net_plus_reset correctly by adding reset value to net count', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $user = User::factory()->create();

        // Create single reset with a specific value
        $area->areaSingleResets()->create([
            'reset_value' => 75,
            'effective_at' => '2024-01-01 12:00:00',
            'created_by' => $user->id,
        ]);

        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval count after reset with known net value
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 13:00:00',
            'ts_to' => '2024-01-01 13:15:00',
            'count_in' => 20,
            'count_out' => 8,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        // Net count: 20 - 8 = 12
        // Reset value: 75
        // Net plus reset should be: 12 + 75 = 87 (NOT 12 - 75 = -63)
        expect($result)->toBeArray()
            ->and($result['in'])->toBe(20)
            ->and($result['out'])->toBe(8)
            ->and($result['net'])->toBe(12)
            ->and($result['last_reset_value'])->toBe(75)
            ->and($result['net_plus_reset'])->toBe(87); // This specifically tests the + operation
    });

    it('returns zero counts when no assignments exist', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['in', 'out', 'net', 'last_reset_type', 'last_reset_at', 'last_reset_value', 'net_plus_reset'])
            ->and($result['in'])->toBe(0)
            ->and($result['out'])->toBe(0)
            ->and($result['net'])->toBe(0)
            ->and($result['net_plus_reset'])->toBe($result['net'] + $result['last_reset_value']);
    });

    it('returns zero counts when no interval counts exist', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create sensor and assignment but no interval counts
        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['in', 'out', 'net', 'last_reset_type', 'last_reset_at', 'last_reset_value', 'net_plus_reset'])
            ->and($result['in'])->toBe(0)
            ->and($result['out'])->toBe(0)
            ->and($result['net'])->toBe(0)
            ->and($result['net_plus_reset'])->toBe($result['net'] + $result['last_reset_value']);
    });

    it('uses event start time when no single reset exists', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval count before event start (should be excluded)
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 09:00:00',
            'ts_to' => '2024-01-01 09:15:00',
            'count_in' => 5,
            'count_out' => 2,
        ]);

        // Create interval count after event start (should be included)
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 10,
            'count_out' => 3,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result['in'])->toBe(10)
            ->and($result['out'])->toBe(3)
            ->and($result['net'])->toBe(7);
    });

    it('uses latest single reset time as start when reset exists', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $user = User::factory()->create();

        // Create single reset
        $area->areaSingleResets()->create([
            'reset_value' => 50,
            'effective_at' => '2024-01-01 12:00:00',
            'created_by' => $user->id,
        ]);

        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval count before reset (should be excluded)
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 5,
            'count_out' => 2,
        ]);

        // Create interval count after reset (should be included)
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 13:00:00',
            'ts_to' => '2024-01-01 13:15:00',
            'count_in' => 10,
            'count_out' => 3,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result['in'])->toBe(10)
            ->and($result['out'])->toBe(3)
            ->and($result['net'])->toBe(7);
    });

    it('aggregates counts from multiple assignments', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // Create first sensor and assignment
        $sensor1 = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor1->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create second sensor and assignment
        $sensor2 = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor2->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval counts for first sensor
        IntervalCount::factory()->create([
            'sensor_id' => $sensor1->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 10,
            'count_out' => 5,
        ]);

        // Create interval counts for second sensor
        IntervalCount::factory()->create([
            'sensor_id' => $sensor2->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 15,
            'count_out' => 8,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result['in'])->toBe(25)
            ->and($result['out'])->toBe(13)
            ->and($result['net'])->toBe(12);
    });

    it('loads required relationships', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Call the method
        $this->service->calculateAreaDebugCounts($area);

        // Verify relationships are loaded
        expect($area->relationLoaded('assignments'))->toBeTrue()
            ->and($area->relationLoaded('event'))->toBeTrue()
            ->and($area->relationLoaded('areaSingleResets'))->toBeTrue();

        // Verify nested relationships
        $area->assignments->each(function ($assignment) {
            expect($assignment->relationLoaded('sensor'))->toBeTrue();
            $assignment->sensor->intervalCounts->each(function ($intervalCount) {
                expect($intervalCount)->toBeInstanceOf(IntervalCount::class);
            });
        });
    });

    it('handles negative net counts correctly', function () {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        $sensor = Sensor::factory()->create();
        Assignment::factory()->create([
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => '2024-01-01 09:00:00',
            'active_to' => '2024-01-01 19:00:00',
        ]);

        // Create interval count where out > in
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => '2024-01-01 11:00:00',
            'ts_to' => '2024-01-01 11:15:00',
            'count_in' => 5,
            'count_out' => 15,
        ]);

        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result['in'])->toBe(5)
            ->and($result['out'])->toBe(15)
            ->and($result['net'])->toBe(-10);
    });
});

// Additional coverage for calculateAreaDebugCounts fallback

describe('calculateAreaDebugCounts fallback coverage', function () {
    it('uses event_start fallback when now is before event start', function () {
        // Freeze time before the event start so no resets exist at or before now
        Carbon::setTestNow('2024-01-01 09:00:00');

        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'starts_at' => '2024-01-01 10:00:00',
            'ends_at' => '2024-01-01 18:00:00',
        ]);
        $area = Area::factory()->create([
            'event_id' => $event->id,
        ]);

        // No assignments/intervals are needed to trigger the fallback; counts should be zero
        $result = $this->service->calculateAreaDebugCounts($area);

        expect($result)->toBeArray()
            ->and($result['in'])->toBe(0)
            ->and($result['out'])->toBe(0)
            ->and($result['net'])->toBe(0)
            ->and($result['last_reset_type'])->toBe('event_start')
            ->and($result['last_reset_value'])->toBe(0)
            ->and($result['net_plus_reset'])->toBe(0)
            ->and($result['last_reset_at']->toDateTimeString())->toBe('2024-01-01 10:00:00');

        // Reset frozen time to avoid impacting other tests
        Carbon::setTestNow();
    });
});
