<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\Peoplecount\Event;
use App\Models\User;
use App\Services\Peoplecount\AreaResetService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(AreaResetService::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    $this->service = new AreaResetService;
});

describe('createSingleReset', function () {
    it('creates a single reset for an area', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        // Set the permissions organization ID
        setPermissionsOrgId($organization->id);

        // Mock Auth::id() to return the user ID
        \Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn($user->id);

        $attributes = [
            'reset_value' => 50,
            'effective_at' => '2025-07-27 15:00:00',
            'notes' => 'Manual reset for testing',
        ];

        $reset = $this->service->createSingleReset($area, $attributes);

        expect($reset)->toBeInstanceOf(AreaSingleReset::class);
        expect($reset->area_id)->toBe($area->id);
        expect($reset->reset_value)->toBe(50);
        expect($reset->notes)->toBe('Manual reset for testing');
        expect($reset->created_by)->toBe($user->id);
        expect($reset->effective_at)->toBeInstanceOf(Carbon::class);
    });

    it('converts effective_at to UTC for storage', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Mock Auth::id() to return the user ID
        \Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn($user->id);

        $localTime = '2025-07-27 15:00:00';
        $attributes = [
            'reset_value' => 25,
            'effective_at' => $localTime,
            'notes' => null,
        ];

        $reset = $this->service->createSingleReset($area, $attributes);

        expect($reset->effective_at->timezone->getName())->toBe('UTC');
    });

    it('throws authorization exception for area from different organization', function () {
        $user = User::factory()->create();

        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $attributes = [
            'reset_value' => 50,
            'effective_at' => '2025-07-27 15:00:00',
            'notes' => 'Should fail',
        ];

        expect(fn () => $this->service->createSingleReset($area, $attributes))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });

    it('allows global organization to create resets for any area', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        // Mock Auth::id() to return the user ID
        \Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn($user->id);

        $attributes = [
            'reset_value' => 100,
            'effective_at' => '2025-07-27 15:00:00',
            'notes' => 'Global admin reset',
        ];

        $reset = $this->service->createSingleReset($area, $attributes);

        expect($reset)->toBeInstanceOf(AreaSingleReset::class);
        expect($reset->reset_value)->toBe(100);
    });
});

describe('getAreaResets', function () {
    it('returns resets for an area ordered by effective_at desc', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Create resets with different timestamps
        $reset1 = AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'effective_at' => now()->subHours(2),
            'created_by' => $user->id,
        ]);
        $reset2 = AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'effective_at' => now()->subHour(),
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets)->toHaveCount(2);
        expect($resets->first()->id)->toBe($reset2->id); // Most recent first
        expect($resets->last()->id)->toBe($reset1->id);
    });

    it('loads createdBy relationship', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'created_by' => $user->id,
        ]);

        $resets = $this->service->getAreaResets($area);

        expect($resets->first()->relationLoaded('createdBy'))->toBeTrue();
        expect($resets->first()->createdBy->id)->toBe($user->id);
    });

    it('throws authorization exception for area from different organization', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        expect(fn () => $this->service->getAreaResets($area))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });
});

describe('deleteSingleReset', function () {
    it('deletes a single reset', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $reset = AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'created_by' => $user->id,
        ]);

        $this->service->deleteSingleReset($reset);

        $this->assertDatabaseMissing('peoplecount_area_single_resets', [
            'id' => $reset->id,
        ]);
    });

    it('throws authorization exception for reset from different organization', function () {
        $user = User::factory()->create();

        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $reset = AreaSingleReset::factory()->create([
            'area_id' => $area->id,
            'created_by' => $user->id,
        ]);

        expect(fn () => $this->service->deleteSingleReset($reset))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });
});

describe('verifyAreaBelongsToCurrentOrganization', function () {
    it('loads event relationship if not already loaded', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Create a fresh area instance without loaded relationships
        $freshArea = \App\Models\Peoplecount\Area::query()->find($area->id);
        expect($freshArea->relationLoaded('event'))->toBeFalse();

        // This should not throw and should load the relationship
        $this->service->getAreaResets($freshArea);

        expect($freshArea->relationLoaded('event'))->toBeTrue();
    });

    it('does not load event relationship if already loaded', function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Pre-load the event relationship
        $area->load('event');
        expect($area->relationLoaded('event'))->toBeTrue();

        // This should not throw and should not need to load the relationship again
        $this->service->getAreaResets($area);

        expect($area->relationLoaded('event'))->toBeTrue();
    });

    it('skips relationship loading when event is already loaded', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Create a spy to track if load() is called
        $areaSpy = \Mockery::spy($area);
        $areaSpy->shouldReceive('relationLoaded')->with('event')->andReturn(true);
        $areaSpy->shouldReceive('getAttribute')->with('event')->andReturn($event);
        $areaSpy->shouldNotReceive('load');

        // Mock the area relationship
        $areaSpy->shouldReceive('areaSingleResets')->andReturn(
            \Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class)
                ->shouldReceive('with')->with('createdBy')->andReturnSelf()
                ->shouldReceive('orderBy')->with('effective_at', 'desc')->andReturnSelf()
                ->shouldReceive('get')->andReturn(collect())
                ->getMock()
        );

        $this->service->getAreaResets($areaSpy);
    });

    it('calls load method when event relationship is not loaded', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Create a spy to track if load() is called
        $areaSpy = \Mockery::spy($area);
        $areaSpy->shouldReceive('relationLoaded')->with('event')->andReturn(false);
        $areaSpy->shouldReceive('load')->with('event')->once()->andReturnSelf();
        $areaSpy->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock the area relationship
        $areaSpy->shouldReceive('areaSingleResets')->andReturn(
            \Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class)
                ->shouldReceive('with')->with('createdBy')->andReturnSelf()
                ->shouldReceive('orderBy')->with('effective_at', 'desc')->andReturnSelf()
                ->shouldReceive('get')->andReturn(collect())
                ->getMock()
        );

        $this->service->getAreaResets($areaSpy);
    });
});

describe('getAreaRecurringResets', function () {
    it('returns recurring resets for an area ordered by created_at desc', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        // Create recurring resets with different timestamps
        $reset1 = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
            'created_at' => now()->subHours(2),
        ]);
        $reset2 = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
            'created_at' => now()->subHour(),
        ]);

        $resets = $this->service->getAreaRecurringResets($area);

        expect($resets)->toHaveCount(2);
        expect($resets->first()->id)->toBe($reset2->id); // Most recent first
        expect($resets->last()->id)->toBe($reset1->id);
    });

    it('loads area and event relationships', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
        ]);

        $resets = $this->service->getAreaRecurringResets($area);

        expect($resets->first()->relationLoaded('area'))->toBeTrue();
        expect($resets->first()->area->id)->toBe($area->id);
    });

    it('throws authorization exception for area from different organization', function () {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        expect(fn () => $this->service->getAreaRecurringResets($area))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });
});

describe('createRecurringReset', function () {
    it('creates a recurring reset for an area', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $attributes = [
            'reset_value' => 50,
            'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Daily reset for testing',
        ];

        $reset = $this->service->createRecurringReset($area, $attributes);

        expect($reset)->toBeInstanceOf(AreaRecurringReset::class);
        expect($reset->area_id)->toBe($area->id);
        expect($reset->reset_value)->toBe(50);
        expect($reset->rrule)->toBe('FREQ=DAILY;INTERVAL=1');
        expect($reset->timezone)->toBe('Europe/Zurich');
        expect($reset->notes)->toBe('Daily reset for testing');
    });

    it('validates RRULE format', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $attributes = [
            'reset_value' => 50,
            'rrule' => 'INVALID_RRULE',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Should fail',
        ];

        try {
            $this->service->createRecurringReset($area, $attributes);
            expect(false)->toBeTrue('Expected InvalidArgumentException to be thrown');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            expect($invalidArgumentException->getMessage())->toStartWith('Invalid RRULE format: ');
            expect($invalidArgumentException->getMessage())->toContain('INVALID_RRULE');
        }
    });

    it('throws authorization exception for area from different organization', function () {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $attributes = [
            'reset_value' => 50,
            'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Should fail',
        ];

        expect(fn () => $this->service->createRecurringReset($area, $attributes))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });

    it('allows global organization to create recurring resets for any area', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        $attributes = [
            'reset_value' => 100,
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Global admin recurring reset',
        ];

        $reset = $this->service->createRecurringReset($area, $attributes);

        expect($reset)->toBeInstanceOf(AreaRecurringReset::class);
        expect($reset->reset_value)->toBe(100);
    });
});

describe('updateRecurringReset', function () {
    it('updates a recurring reset', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $reset = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
            'reset_value' => 25,
            'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Original notes',
        ]);

        $attributes = [
            'reset_value' => 75,
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'timezone' => 'America/New_York',
            'notes' => 'Updated notes',
        ];

        $updatedReset = $this->service->updateRecurringReset($reset, $attributes);

        expect($updatedReset->reset_value)->toBe(75);
        expect($updatedReset->rrule)->toBe('FREQ=WEEKLY;BYDAY=MO');
        expect($updatedReset->timezone)->toBe('America/New_York');
        expect($updatedReset->notes)->toBe('Updated notes');
    });

    it('validates RRULE format on update', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $reset = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
        ]);

        $attributes = [
            'reset_value' => 50,
            'rrule' => 'INVALID_RRULE',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Should fail',
        ];

        try {
            $this->service->updateRecurringReset($reset, $attributes);
            expect(false)->toBeTrue('Expected InvalidArgumentException to be thrown');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            expect($invalidArgumentException->getMessage())->toStartWith('Invalid RRULE format: ');
            expect($invalidArgumentException->getMessage())->toContain('INVALID_RRULE');
        }
    });

    it('throws authorization exception for reset from different organization', function () {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $reset = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
        ]);

        $attributes = [
            'reset_value' => 50,
            'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Should fail',
        ];

        expect(fn () => $this->service->updateRecurringReset($reset, $attributes))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });
});

describe('deleteRecurringReset', function () {
    it('deletes a recurring reset', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($organization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($organization->id);

        $reset = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
        ]);

        $this->service->deleteRecurringReset($reset);

        $this->assertDatabaseMissing('peoplecount_area_recurring_resets', [
            'id' => $reset->id,
        ]);
    });

    it('throws authorization exception for reset from different organization', function () {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $reset = AreaRecurringReset::factory()->create([
            'area_id' => $area->id,
        ]);

        expect(fn () => $this->service->deleteRecurringReset($reset))
            ->toThrow(AuthorizationException::class, 'You are not authorized to access this area.');
    });
});

describe('parseRRule', function () {
    it('parses a valid RRULE string', function () {
        $rrule = 'FREQ=DAILY;INTERVAL=1';
        $parsed = $this->service->parseRRule($rrule);

        expect($parsed)->toBeInstanceOf(\RRule\RRule::class);
    });

    it('throws exception for invalid RRULE', function () {
        $rrule = 'INVALID_RRULE';

        expect(fn () => $this->service->parseRRule($rrule))
            ->toThrow(\Exception::class);
    });
});

describe('getNextResetOccurrences', function () {
    it('returns next occurrences for a valid RRULE', function () {
        $rrule = 'FREQ=DAILY;INTERVAL=1;COUNT=3';
        $occurrences = $this->service->getNextResetOccurrences($rrule, 3);

        expect($occurrences)->toHaveCount(3);
        expect($occurrences[0])->toBeInstanceOf(\DateTime::class);
        expect($occurrences[1])->toBeInstanceOf(\DateTime::class);
        expect($occurrences[2])->toBeInstanceOf(\DateTime::class);
    });

    it('limits occurrences to specified limit', function () {
        $rrule = 'FREQ=DAILY;INTERVAL=1';
        $occurrences = $this->service->getNextResetOccurrences($rrule, 2);

        expect($occurrences)->toHaveCount(2);
    });

    it('returns occurrences efficiently using getBetween method', function () {
        // Test with a longer-running rule to ensure we're using the efficient method
        $rrule = 'FREQ=DAILY;INTERVAL=1';
        $start = microtime(true);
        $occurrences = $this->service->getNextResetOccurrences($rrule, 10);
        $end = microtime(true);

        expect($occurrences)->toHaveCount(10);
        // Should complete quickly (less than 100ms for 10 occurrences)
        expect($end - $start)->toBeLessThan(0.1);
    });

    it('handles timezone-aware RRULE correctly', function () {
        // TODO: Add proper DTSTART with timezone when implementing timezone support
        $rrule = 'FREQ=DAILY;INTERVAL=1;BYHOUR=9;BYMINUTE=0';
        $occurrences = $this->service->getNextResetOccurrences($rrule, 3);

        expect($occurrences)->toHaveCount(3);

        // Verify that occurrences are spaced 24 hours apart
        $diff1 = $occurrences[1]->getTimestamp() - $occurrences[0]->getTimestamp();
        $diff2 = $occurrences[2]->getTimestamp() - $occurrences[1]->getTimestamp();

        expect($diff1)->toBe(86400); // 24 hours in seconds
        expect($diff2)->toBe(86400);
    });

    it('returns empty array for RRULE with no future occurrences', function () {
        // Create an RRULE that ended in the past
        $pastDate = (new \DateTime)->sub(new \DateInterval('P1Y'))->format('Ymd\THis\Z');
        $rrule = 'FREQ=DAILY;UNTIL='.$pastDate;

        $occurrences = $this->service->getNextResetOccurrences($rrule, 5);

        expect($occurrences)->toHaveCount(0);
    });
});
