<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
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
});
