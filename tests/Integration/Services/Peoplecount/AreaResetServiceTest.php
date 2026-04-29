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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

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
        Auth::shouldReceive('id')->andReturn($user->id);

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
        Auth::shouldReceive('id')->andReturn($user->id);

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
        Auth::shouldReceive('id')->andReturn($user->id);

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
        $freshArea = Area::query()->find($area->id);
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
        $areaSpy = Mockery::spy($area);
        $areaSpy->shouldReceive('relationLoaded')->with('event')->andReturn(true);
        $areaSpy->shouldReceive('getAttribute')->with('event')->andReturn($event);
        $areaSpy->shouldNotReceive('load');

        // Mock the area relationship
        $areaSpy->shouldReceive('areaSingleResets')->andReturn(
            Mockery::mock(HasMany::class)
                ->shouldReceive('with')->with('createdBy')->andReturnSelf()
                ->shouldReceive('latest')->with('effective_at')->andReturnSelf()
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
        $areaSpy = Mockery::spy($area);
        $areaSpy->shouldReceive('relationLoaded')->with('event')->andReturn(false);
        $areaSpy->shouldReceive('load')->with('event')->once()->andReturnSelf();
        $areaSpy->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock the area relationship
        $areaSpy->shouldReceive('areaSingleResets')->andReturn(
            Mockery::mock(HasMany::class)
                ->shouldReceive('with')->with('createdBy')->andReturnSelf()
                ->shouldReceive('latest')->with('effective_at')->andReturnSelf()
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
            'reset_time' => '08:00',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Daily reset for testing',
        ];

        $reset = $this->service->createRecurringReset($area, $attributes);

        expect($reset)->toBeInstanceOf(AreaRecurringReset::class);
        expect($reset->area_id)->toBe($area->id);
        expect($reset->reset_value)->toBe(50);
        expect($reset->reset_time)->toBe('08:00');
        expect($reset->timezone)->toBe('Europe/Zurich');
        expect($reset->notes)->toBe('Daily reset for testing');
    });

    it('throws authorization exception for area from different organization', function () {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $event = Event::factory()->withOrganization($otherOrganization)->create();
        $area = Area::factory()->withEvent($event)->create();

        setPermissionsOrgId($userOrganization->id);

        $attributes = [
            'reset_value' => 50,
            'reset_time' => '08:00',
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
            'reset_time' => '14:30',
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
            'reset_time' => '08:00',
            'timezone' => 'Europe/Zurich',
            'notes' => 'Original notes',
        ]);

        $attributes = [
            'reset_value' => 75,
            'reset_time' => '14:30',
            'timezone' => 'America/New_York',
            'notes' => 'Updated notes',
        ];

        $updatedReset = $this->service->updateRecurringReset($reset, $attributes);

        expect($updatedReset->reset_value)->toBe(75);
        expect($updatedReset->reset_time)->toBe('14:30');
        expect($updatedReset->timezone)->toBe('America/New_York');
        expect($updatedReset->notes)->toBe('Updated notes');
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
            'reset_time' => '08:00',
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
