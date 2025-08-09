<?php

use App\Http\Controllers\Widgets\PeoplecountActiveAreaCountsWidgetController;
use App\Http\Requests\Widgets\Peoplecount\ActiveAreaCountsIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('returns active area counts for an organization', function () {
    // Set a fixed time for consistent testing
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    // Create an active event (current time is within the event period)
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(1),
        'ends_at' => Carbon::now()->addHours(1),
    ]);

    // Create an area for the event
    $area = Area::factory()->create([
        'event_id' => $event->id,
        'name' => 'Test Area',
    ]);

    // Create an aggregated count for the area
    AreaAggregatedCount::factory()->create([
        'area_id' => $area->id,
        'period_start' => Carbon::now()->subMinutes(10),
        'period_end' => Carbon::now(),
        'count' => 42,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-aggregation.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'event_name',
                'count',
                'last_updated',
            ],
        ])
        ->assertJsonPath('0.id', $area->id)
        ->assertJsonPath('0.name', 'Test Area')
        ->assertJsonPath('0.event_name', $event->name)
        ->assertJsonPath('0.count', 42);

    // Reset the fixed time
    Carbon::setTestNow();
});

it('returns empty array when no active events exist', function () {
    // Set a fixed time for consistent testing
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    // Create a past event
    $pastEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(3),
        'ends_at' => Carbon::now()->subHours(1),
    ]);

    // Create an area for the past event
    Area::factory()->create([
        'event_id' => $pastEvent->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-aggregation.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(0);

    // Reset the fixed time
    Carbon::setTestNow();
});

it('returns 403 when user does not have permission', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('peoplecount.area-aggregation.index', ['organization' => $org->slug]));

    $response->assertStatus(403);
});

it('uses the correct form request', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.area-aggregation.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // index
    test()->assertActionUsesFormRequest(
        PeoplecountActiveAreaCountsWidgetController::class,
        'index',
        ActiveAreaCountsIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.area-aggregation.index',
        ActiveAreaCountsIndexRequest::class);
});
