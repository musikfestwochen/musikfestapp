<?php

use App\Http\Controllers\Widgets\PeoplecountAreaCountHistoryWidgetController;
use App\Http\Requests\Widgets\Peoplecount\AreaCountHistoryIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('returns area count history for active areas of an organization', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(1),
        'ends_at' => Carbon::now()->addHours(1),
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
        'name' => 'History Area',
    ]);

    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subMinutes(30),
        'period_end' => Carbon::now()->subMinutes(20),
        'count' => 10,
    ]);
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subMinutes(20),
        'period_end' => Carbon::now()->subMinutes(10),
        'count' => 25,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'event_name',
                'data' => [
                    '*' => ['time', 'count'],
                ],
            ],
        ])
        ->assertJsonPath('0.id', $area->id)
        ->assertJsonPath('0.name', 'History Area')
        ->assertJsonPath('0.event_name', $event->name)
        ->assertJsonCount(2, '0.data')
        ->assertJsonPath('0.data.0.count', 10)
        ->assertJsonPath('0.data.1.count', 25);

    Carbon::setTestNow();
});

it('does not return data outside the requested range', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(5),
        'ends_at' => Carbon::now()->addHours(5),
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Inside the window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subMinutes(50),
        'period_end' => Carbon::now()->subMinutes(40),
        'count' => 7,
    ]);

    // Outside the requested window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subHours(3),
        'period_end' => Carbon::now()->subHours(2)->subMinutes(50),
        'count' => 99,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Carbon::now()->subHour()->toIso8601String(),
            'to' => Carbon::now()->toIso8601String(),
        ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 7);

    Carbon::setTestNow();
});

it('includes the in-progress bucket whose period_end is in the future', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    $area = Area::factory()->create(['event_id' => $event->id]);

    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subMinutes(5),
        'period_end' => Carbon::now()->addMinutes(5),
        'count' => 42,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 42)
        ->assertJsonPath('0.data.0.time', Carbon::now()->setTimezone('UTC')->toIso8601String());

    Carbon::setTestNow();
});

it('only returns areas belonging to currently active events', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $pastEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(5),
        'ends_at' => Carbon::now()->subHours(2),
    ]);
    $pastArea = Area::factory()->create(['event_id' => $pastEvent->id]);
    AreaAggregatedCount::factory()->withArea($pastArea)->create([
        'period_start' => Carbon::now()->subHours(4),
        'period_end' => Carbon::now()->subHours(4)->addMinutes(10),
        'count' => 5,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(0);

    Carbon::setTestNow();
});

it('does not leak areas from other organizations', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();

    $otherEvent = Event::factory()->create([
        'organization_id' => $otherOrg->id,
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
    ]);
    $otherArea = Area::factory()->create(['event_id' => $otherEvent->id]);
    AreaAggregatedCount::factory()->withArea($otherArea)->create([
        'period_start' => Carbon::now()->subMinutes(10),
        'period_end' => Carbon::now(),
        'count' => 42,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(0);

    Carbon::setTestNow();
});

it('returns 403 when user does not have permission', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(403);
});

it('rejects invalid date parameters', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => 'not-a-date',
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from']);
});

it('rejects history ranges above maximum allowed duration', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Carbon::now()->subHours(25)->toIso8601String(),
            'to' => Carbon::now()->toIso8601String(),
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['to']);

    Carbon::setTestNow();
});

it('rejects an inverted history range', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Carbon::now()->toIso8601String(),
            'to' => Carbon::now()->subHour()->toIso8601String(),
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from', 'to']);

    Carbon::setTestNow();
});

it('returns areas in deterministic name order', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    Area::factory()->create(['event_id' => $event->id, 'name' => 'Charlie']);
    Area::factory()->create(['event_id' => $event->id, 'name' => 'Alpha']);
    Area::factory()->create(['event_id' => $event->id, 'name' => 'Bravo']);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonPath('0.name', 'Alpha')
        ->assertJsonPath('1.name', 'Bravo')
        ->assertJsonPath('2.name', 'Charlie');

    Carbon::setTestNow();
});

it('allows history ranges at maximum allowed duration', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Carbon::now()->subHours(24)->toIso8601String(),
            'to' => Carbon::now()->toIso8601String(),
        ]));

    $response->assertStatus(200);

    Carbon::setTestNow();
});

it('defaults to the safe one-hour history range when no dates are provided', function () {
    Carbon::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHours(5),
        'ends_at' => Carbon::now()->addHours(5),
    ]);

    $area = Area::factory()->create(['event_id' => $event->id]);

    // Inside the implicit one-hour window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subMinutes(30),
        'period_end' => Carbon::now()->subMinutes(20),
        'count' => 11,
    ]);

    // Outside the implicit one-hour window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Carbon::now()->subHours(3),
        'period_end' => Carbon::now()->subHours(2)->subMinutes(50),
        'count' => 99,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 11);

    Carbon::setTestNow();
});

it('uses the correct form request and middleware', function () {
    test()->assertRouteUsesMiddleware(
        'peoplecount.area-count-history.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    test()->assertActionUsesFormRequest(
        PeoplecountAreaCountHistoryWidgetController::class,
        'index',
        AreaCountHistoryIndexRequest::class,
    );

    test()->assertRouteUsesFormRequest(
        'peoplecount.area-count-history.index',
        AreaCountHistoryIndexRequest::class,
    );
});
