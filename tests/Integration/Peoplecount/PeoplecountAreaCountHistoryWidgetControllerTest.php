<?php

use App\Http\Controllers\Widgets\PeoplecountAreaCountHistoryWidgetController;
use App\Http\Requests\Widgets\Peoplecount\AreaCountHistoryIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('returns area count history for active areas of an organization', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHours(1),
        'ends_at' => Date::now()->addHours(1),
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
        'name' => 'History Area',
    ]);

    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subMinutes(30),
        'period_end' => Date::now()->subMinutes(20),
        'count' => 10,
    ]);
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subMinutes(20),
        'period_end' => Date::now()->subMinutes(10),
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

    Date::setTestNow();
});

it('does not return data outside the requested range', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHours(5),
        'ends_at' => Date::now()->addHours(5),
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Inside the window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subMinutes(50),
        'period_end' => Date::now()->subMinutes(40),
        'count' => 7,
    ]);

    // Outside the requested window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subHours(3),
        'period_end' => Date::now()->subHours(2)->subMinutes(50),
        'count' => 99,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Date::now()->subHour()->toIso8601ZuluString('millisecond'),
            'to' => Date::now()->toIso8601ZuluString('millisecond'),
        ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 7);

    Date::setTestNow();
});

it('includes the in-progress bucket whose period_end is in the future', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHour(),
        'ends_at' => Date::now()->addHour(),
    ]);

    $area = Area::factory()->create(['event_id' => $event->id]);

    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subMinutes(5),
        'period_end' => Date::now()->addMinutes(5),
        'count' => 42,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 42)
        ->assertJsonPath('0.data.0.time', Date::now()->setTimezone('UTC')->toIso8601String());

    Date::setTestNow();
});

it('only returns areas belonging to currently active events', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $pastEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHours(5),
        'ends_at' => Date::now()->subHours(2),
    ]);
    $pastArea = Area::factory()->create(['event_id' => $pastEvent->id]);
    AreaAggregatedCount::factory()->withArea($pastArea)->create([
        'period_start' => Date::now()->subHours(4),
        'period_end' => Date::now()->subHours(4)->addMinutes(10),
        'count' => 5,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(0);

    Date::setTestNow();
});

it('does not leak areas from other organizations', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();

    $otherEvent = Event::factory()->create([
        'organization_id' => $otherOrg->id,
        'starts_at' => Date::now()->subHour(),
        'ends_at' => Date::now()->addHour(),
    ]);
    $otherArea = Area::factory()->create(['event_id' => $otherEvent->id]);
    AreaAggregatedCount::factory()->withArea($otherArea)->create([
        'period_start' => Date::now()->subMinutes(10),
        'period_end' => Date::now(),
        'count' => 42,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(0);

    Date::setTestNow();
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

    // Naive datetime strings without UTC marker are rejected too
    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => '2025-08-04 21:08:00',
            'to' => '2025-08-04 22:08:00',
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from', 'to']);
});

it('rejects history ranges above maximum allowed duration', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Date::now()->subHours(25)->toIso8601ZuluString('millisecond'),
            'to' => Date::now()->toIso8601ZuluString('millisecond'),
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['to']);

    Date::setTestNow();
});

it('rejects an inverted history range', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Date::now()->toIso8601ZuluString('millisecond'),
            'to' => Date::now()->subHour()->toIso8601ZuluString('millisecond'),
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from', 'to']);

    Date::setTestNow();
});

it('returns areas in deterministic name order', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHour(),
        'ends_at' => Date::now()->addHour(),
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

    Date::setTestNow();
});

it('allows history ranges at maximum allowed duration', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', [
            'organization' => $org->slug,
            'from' => Date::now()->subHours(24)->toIso8601ZuluString('millisecond'),
            'to' => Date::now()->toIso8601ZuluString('millisecond'),
        ]));

    $response->assertStatus(200);

    Date::setTestNow();
});

it('defaults to the safe one-hour history range when no dates are provided', function () {
    Date::setTestNow('2025-08-04 22:08:00');

    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Date::now()->subHours(5),
        'ends_at' => Date::now()->addHours(5),
    ]);

    $area = Area::factory()->create(['event_id' => $event->id]);

    // Inside the implicit one-hour window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subMinutes(30),
        'period_end' => Date::now()->subMinutes(20),
        'count' => 11,
    ]);

    // Outside the implicit one-hour window
    AreaAggregatedCount::factory()->withArea($area)->create([
        'period_start' => Date::now()->subHours(3),
        'period_end' => Date::now()->subHours(2)->subMinutes(50),
        'count' => 99,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('peoplecount.area-count-history.index', ['organization' => $org->slug]));

    $response->assertStatus(200)
        ->assertJsonCount(1, '0.data')
        ->assertJsonPath('0.data.0.count', 11);

    Date::setTestNow();
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
