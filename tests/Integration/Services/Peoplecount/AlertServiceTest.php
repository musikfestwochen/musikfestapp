<?php

declare(strict_types=1);

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use App\Models\User;
use App\Services\Peoplecount\AlertService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

covers(AlertService::class);

// Test helpers and DTO
final class Graph implements ArrayAccess
{
    public function __construct(
        public readonly Organization $org,
        public readonly Collection $users,
        public readonly Event $event,
        public readonly Area $area,
        public readonly Area $area2,
        public readonly Organization $otherOrg,
        public readonly Event $otherEvent,
        public readonly Area $otherArea,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (! is_string($offset) || ! property_exists($this, $offset)) {
            throw new OutOfBoundsException('Invalid graph key: '.(string) $offset);
        }

        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Graph is readonly');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Graph is readonly');
    }
}

function enforceSqliteForeignKeys(): void
{
    try {
        DB::statement('PRAGMA foreign_keys = ON');
    } catch (Throwable $e) {
        // noop for non-sqlite environments
    }
}

function svc(): AlertService
{
    return app(AlertService::class);
}

function signIn(User $user): void
{
    actingAs($user);
}

function defaultAlertAttrs(?int $cooldownMinutes = 60): array
{
    return [
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => $cooldownMinutes,
    ];
}

function recipientsCsv(iterable $ids): string
{
    $list = [];
    foreach ($ids as $id) {
        $list[] = (string) $id;
    }
    if (count($list) === 0) {
        return '';
    }

    return implode(', ', $list).', '.$list[0];
}

beforeEach(function () {
    enforceSqliteForeignKeys();
});

/**
 * Helper: build a consistent object graph
 * org -> users -> event(org) -> area(event)
 * Optionally create another area in the same org and a second org+area for cross-org tests
 */
function buildGraph(int $userCount = 3): Graph
{
    $org = Organization::factory()->create();

    // Create users and attach to org
    $users = User::factory($userCount)->create();
    $org->users()->attach($users->pluck('id'));

    // Create event within org and two areas within the event
    $event = Event::factory()->withOrganization($org)->create();
    $area = Area::factory()->withEvent($event)->create();
    $area2 = Area::factory()->withEvent($event)->create();

    // Second org with its own event and area
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->withOrganization($otherOrg)->create();
    $otherArea = Area::factory()->withEvent($otherEvent)->create();

    return new Graph(
        org: $org,
        users: $users,
        event: $event,
        area: $area,
        area2: $area2,
        otherOrg: $otherOrg,
        otherEvent: $otherEvent,
        otherArea: $otherArea,
    );
}

// 1. getAreaAlerts: returns only alerts for area, with relations loaded
it('getAreaAlerts returns only alerts for the given area and eager loads relations', function () {
    $svc = svc();
    $graph = buildGraph();
    $user = $graph->users->first();
    signIn($user);

    // create alerts for both areas
    $a1Alert1 = Alert::factory()->for($graph->area)->create();
    $a1Alert2 = Alert::factory()->for($graph->area)->create();
    $a2Alert = Alert::factory()->for($graph->area2)->create();

    $alerts = $svc->getAreaAlerts($graph->org, $graph->area);

    expect($alerts->pluck('id')->all())
        ->toEqualCanonicalizing([$a1Alert1->id, $a1Alert2->id])
        ->and($alerts->every(fn (Alert $a) => $a->relationLoaded('creator') && $a->relationLoaded('recipients')))
        ->toBeTrue();
});

// 2. storeAreaAlert: forces area_id and sets created_by
it('storeAreaAlert forces area_id and sets created_by', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    $attributes = array_merge(
        defaultAlertAttrs(300),
        [
            'area_id' => $graph->area2->id, // wrong on purpose, should be overridden
            'occupancy_alert_threshold' => 100,
        ],
    );

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, $attributes);

    $alert->refresh();
    expect($alert->area_id)->toBe($graph->area->id)
        ->and($alert->created_by)->toBe($actor->id);

    // DB assertions
    $this->assertDatabaseHas('peoplecount_alerts', [
        'id' => $alert->id,
        'area_id' => $graph->area->id,
        'created_by' => $actor->id,
    ]);
});

// 3. storeAreaAlert: syncs recipients (all in org)
it('storeAreaAlert syncs recipients from mixed input and dedupes', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 3);
    $actor = $graph->users->first();
    /** @var Collection<int,int> $recipientIds */
    $recipientIds = $graph->users->slice(0, 2)->pluck('id');

    signIn($actor);

    $attributes = array_merge(
        defaultAlertAttrs(60),
        [
            // pass recipients as CSV string with spaces and duplicates
            'recipients' => recipientsCsv($recipientIds),
        ],
    );

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, $attributes);

    $alert->load('recipients');
    expect($alert->recipients->pluck('id')->all())
        ->toEqualCanonicalizing($recipientIds->all());

    // Pivot assertions: exactly two rows
    $this->assertDatabaseCount('peoplecount_alert_user', 2);
});

// 4. storeAreaAlert: empty recipients clears pivot
it('storeAreaAlert with empty recipients clears pivot', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    // first create with some recipients
    $r1 = $graph->users[0]->id;
    $r2 = $graph->users[1]->id;

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$r1, $r2],
        ],
    ));

    // now clear by passing empty string
    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => '',
    ]);

    $alert->load('recipients');
    expect($alert->recipients)->toHaveCount(0);
    $this->assertDatabaseCount('peoplecount_alert_user', 0);
});

// 5. updateAreaAlert: keeps area_id stable and updates attributes
it('updateAreaAlert keeps area_id stable and updates attributes', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    $alert = Alert::factory()->for($graph->area)->create([
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
    ]);

    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'area_id' => $graph->area2->id, // should be ignored/overridden
        'cooldown_minutes' => 240,
        'occupancy_alert_threshold' => 555,
    ]);

    $alert->refresh();
    expect($alert->area_id)->toBe($graph->area->id)
        ->and($alert->cooldown_minutes)->toBe(240)
        ->and($alert->occupancy_alert_threshold)->toBe(555);
});

// 6. updateAreaAlert: re-sync recipients
it('updateAreaAlert re-syncs recipients without duplicates', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 4);
    $actor = $graph->users->first();
    signIn($actor);

    $r1 = $graph->users[0]->id;
    $r2 = $graph->users[1]->id;
    $r3 = $graph->users[2]->id;

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$r1, $r2],
        ],
    ));

    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => [$r2, (string) $r3, $r2],
    ]);

    $alert->load('recipients');
    expect($alert->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r2, $r3]);
});

// 7. destroyAreaAlert: deletes when area+org match
it('destroyAreaAlert deletes the alert when area and org match', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    $alert = Alert::factory()->for($graph->area)->create();

    $svc->destroyAreaAlert($graph->org, $graph->area, $alert);

    $this->assertDatabaseMissing('peoplecount_alerts', ['id' => $alert->id]);
});

// 8. Authorization: area not in org throws
it('throws if area is not in organization for all relevant methods', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    // Create alert in other area/org
    $foreignAlert = Alert::factory()->for($graph->otherArea)->create();

    // getAreaAlerts
    expect(fn () => $svc->getAreaAlerts($graph->org, $graph->otherArea))
        ->toThrow(AuthorizationException::class);

    // storeAreaAlert
    expect(fn () => $svc->storeAreaAlert($graph->org, $graph->otherArea, defaultAlertAttrs(60)))
        ->toThrow(AuthorizationException::class);

    // updateAreaAlert (alert belongs to other area)
    expect(fn () => $svc->updateAreaAlert($graph->org, $graph->otherArea, $foreignAlert, [
        'cooldown_minutes' => 90,
    ]))->toThrow(AuthorizationException::class);

    // destroyAreaAlert
    expect(fn () => $svc->destroyAreaAlert($graph->org, $graph->otherArea, $foreignAlert))
        ->toThrow(AuthorizationException::class);
});

// 9. Authorization: alert not in area throws for update/destroy
it('throws if alert does not belong to the given area for update/destroy', function () {
    $svc = svc();
    $graph = buildGraph();
    $actor = $graph->users->first();
    signIn($actor);

    $alertInArea2 = Alert::factory()->for($graph->area2)->create();

    expect(fn () => $svc->updateAreaAlert($graph->org, $graph->area, $alertInArea2, [
        'cooldown_minutes' => 111,
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => $svc->destroyAreaAlert($graph->org, $graph->area, $alertInArea2))
        ->toThrow(AuthorizationException::class);
});

// 10. Authorization: recipients not in org rejected
it('rejects recipients that are not part of the organization on store/update', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 2);
    $actor = $graph->users->first();
    signIn($actor);

    $inOrg = $graph->users[0]->id;
    $outOrgUser = User::factory()->create(); // not attached to org

    // store
    expect(fn () => $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$inOrg, $outOrgUser->id],
        ],
    )))->toThrow(AuthorizationException::class);

    // create a valid alert first, then attempt update
    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$inOrg],
        ],
    ));

    expect(fn () => $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => [$inOrg, $outOrgUser->id],
    ]))->toThrow(AuthorizationException::class);
});

// 11. Idempotency of sync: no duplication
it('recipient sync is idempotent and does not duplicate entries', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 3);
    $actor = $graph->users->first();
    signIn($actor);

    $r1 = $graph->users[0]->id;
    $r2 = $graph->users[1]->id;

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$r1, $r2],
        ],
    ));

    // call update twice with the same list
    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => [$r1, $r2],
    ]);
    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => [$r1, $r2],
    ]);

    $alert->load('recipients');
    expect($alert->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);

    $this->assertDatabaseCount('peoplecount_alert_user', 2);
});

// 12. Normalization cases at integration boundary
it('normalizes recipients from various input shapes', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 3);
    $actor = $graph->users->first();
    signIn($actor);

    $r1 = $graph->users[0]->id;
    $r2 = $graph->users[1]->id;

    // JSON string
    $alert1 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => json_encode([$r1, (string) $r2]),
        ],
    ));
    $alert1->load('recipients');
    expect($alert1->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);

    // CSV string with duplicate
    $alert2 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => "$r1, $r1, $r2",
        ],
    ));
    $alert2->load('recipients');
    expect($alert2->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);

    // Collection of ints and numeric strings
    $alert3 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => Collection::make([$r1, (string) $r2]),
        ],
    ));
    $alert3->load('recipients');
    expect($alert3->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);

    // Whitespace numeric string
    $alert4 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => ' '.$r1.' ',
        ],
    ));
    $alert4->load('recipients');
    expect($alert4->recipients->pluck('id')->all())
        ->toEqual([$r1]);

    // Whitespace CSV tokens
    $alert5 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => ' '.$r1.' ,  '.$r2.' ',
        ],
    ));
    $alert5->load('recipients');
    expect($alert5->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);

    // Partially bracketed JSON-like string -> treated as CSV-like => keeps only numeric parts
    $alert6 = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => '['.$r1.','.$r2,
        ],
    ));
    $alert6->load('recipients');
    expect($alert6->recipients->pluck('id')->all())
        ->toEqual([$r2]);
});

// 13. Recipients update semantics
it('updateAreaAlert without recipients key preserves existing recipients', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 3);
    $actor = $graph->users->first();
    signIn($actor);

    $r1 = $graph->users[0]->id;
    $r2 = $graph->users[1]->id;

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$r1, $r2],
        ],
    ));

    // Update other attribute but omit recipients entirely
    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'cooldown_minutes' => 777,
    ]);

    $alert->load('recipients');
    expect($alert->recipients->pluck('id')->all())
        ->toEqualCanonicalizing([$r1, $r2]);
    $this->assertDatabaseCount('peoplecount_alert_user', 2);
});

it('updateAreaAlert with recipients => null clears recipients', function () {
    $svc = svc();
    $graph = buildGraph(userCount: 3);
    $actor = $graph->users->first();
    signIn($actor);

    $r1 = $graph->users[0]->id;

    $alert = $svc->storeAreaAlert($graph->org, $graph->area, array_merge(
        defaultAlertAttrs(60),
        [
            'recipients' => [$r1],
        ],
    ));

    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'recipients' => null,
    ]);

    $alert->load('recipients');
    expect($alert->recipients)->toHaveCount(0);
    $this->assertDatabaseCount('peoplecount_alert_user', 0);
});

enum _TestRecipientEnum: int
{
    case Five = 5;
}

/**
 * Helper to invoke the private extractRecipientIds via reflection.
 *
 * @return list<int>
 */
function _extract(mixed $input): array
{
    $svc = svc();
    $ref = new ReflectionClass($svc);
    $m = $ref->getMethod('extractRecipientIds');
    $m->setAccessible(true);

    /** @var list<int> $result */
    $result = $m->invoke($svc, $input);

    return $result;
}

it('returns [] for null', function () {
    expect(_extract(null))->toEqual([]);
});

it('returns [] for empty and whitespace strings', function () {
    expect(_extract(''))->toEqual([])
        ->and(_extract('   '))->toEqual([]);
});

it('parses CSV string into unique positive ints', function () {
    expect(_extract('1, 2,3'))->toEqualCanonicalizing([1, 2, 3]);
});

it('parses JSON array string into unique positive ints', function () {
    expect(_extract('[1,2,"3"]'))->toEqualCanonicalizing([1, 2, 3]);
});

it('wraps scalar numeric string into array', function () {
    expect(_extract('7'))->toEqual([7]);
});

it('handles Collections and filters non-numeric', function () {
    expect(_extract(Collection::make([1, '2', 'x'])))->toEqualCanonicalizing([1, 2]);
});

it('accepts objects with id property', function () {
    $obj = (object) ['id' => 5];
    expect(_extract($obj))->toEqual([5]);
});

it('accepts BackedEnum values', function () {
    $enum = _TestRecipientEnum::Five;
    expect(_extract($enum))->toEqual([5]);
});

it('deduplicates values regardless of type', function () {
    expect(_extract([1, '1', 1]))->toEqual([1]);
});

it('filters out non-positive and non-numeric values', function () {
    expect(_extract(['0', '-3', 'a']))->toEqual([]);
});

it('returns [] for invalid JSON array strings', function () {
    expect(_extract('[1,]'))->toEqual([]);
});

it('trims surrounding whitespace for scalar numeric strings', function () {
    expect(_extract(' 7 '))->toEqual([7]);
});

it('treats a partially bracketed string as CSV-like and extracts numeric parts', function () {
    expect(_extract('[1,2'))->toEqual([2]);
});

it('trims whitespace around CSV tokens comprehensively', function () {
    expect(_extract(' 1 ,  2 ,   3 '))->toEqualCanonicalizing([1, 2, 3]);
});

it('always returns integers, not numeric strings', function () {
    expect(_extract(['1', '2']))->toBe([1, 2]);
});

// ===================== New tests for processing alerts =====================

use App\Models\Peoplecount\AreaAggregatedCount;
use App\Notifications\Peoplecount\AreaOccupancyAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Fix time for deterministic alert processing assertions
    if (! Carbon::hasTestNow()) {
        Carbon::setTestNow(Carbon::parse('2025-08-13 12:00:00')->utc());
    }
});

/**
 * Small helper to create an event within org that is currently ongoing and an area with two org users.
 * Returns [org, event, area, u1, u2].
 */
function _setupEventAreaAndUsers(): array
{
    $org = Organization::factory()->create();

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    $u1 = User::factory()->create(['name' => 'Recipient 1']);
    $u2 = User::factory()->create(['name' => 'Recipient 2']);
    $org->users()->attach([$u1->id, $u2->id]);

    return compact('org', 'event', 'area', 'u1', 'u2');
}

function _createAgg(Area $area, string $start, string $end, int $count): AreaAggregatedCount
{
    return AreaAggregatedCount::factory()
        ->withArea($area)
        ->withPeriod(Carbon::parse($start)->utc(), Carbon::parse($end)->utc())
        ->create(['count' => $count]);
}

it('processAlertsForArea sends occupancy alert and updates last_triggered_at when threshold met', function () {
    Notification::fake();

    extract(_setupEventAreaAndUsers());

    /** @var Alert $alert */
    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 100,
        ],
    ));
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Historical below, latest above
    _createAgg($area, '2025-08-13 11:40:00', '2025-08-13 11:50:00', 90);
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 120);

    svc()->processAlertsForArea($area);

    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n) use ($event, $area): bool {
        expect($n->eventName)->toBe($event->name)
            ->and($n->areaName)->toBe($area->name)
            ->and($n->configuredThreshold)->toBe(100)
            ->and($n->currentOccupancy)->toBe(120)
            ->and($n->via($n))->toContain('mail');

        return true;
    });

    $alert->refresh();
    expect($alert->last_triggered_at)->not()->toBeNull()
        ->and($alert->last_triggered_at->equalTo(Carbon::now()))->toBeTrue();
});

it('does not send when no aggregated counts exist or latest below threshold', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 200,
        ],
    ));
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Case 1: no counts
    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();

    // Case 2: latest below threshold
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 150);
    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();
});

it('respects cooldown window for subsequent triggers', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 50,
            'last_triggered_at' => Carbon::now()->subMinutes(30),
        ],
    ));
    $alert->recipients()->attach([$u1->id, $u2->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 300);

    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();
});

it('does not trigger during cooldown even when drop-below condition is satisfied after last trigger', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $threshold = 100;

    // last trigger 30 minutes ago, cooldown 60 => still in cooldown
    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => $threshold,
            'last_triggered_at' => Carbon::now()->subMinutes(30),
        ],
    ));
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Since last trigger: first a drop below threshold, then rise above threshold
    _createAgg($area, '2025-08-13 12:00:00', '2025-08-13 12:10:00', 80);   // below threshold
    _createAgg($area, '2025-08-13 12:10:00', '2025-08-13 12:20:00', 150);  // above threshold (latest)

    // Even though drop-below condition is satisfied, cooldown should prevent sending
    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();
});

it('requires a drop below threshold since last trigger before re-sending', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $threshold = 100;

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(10),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => $threshold,
            'last_triggered_at' => Carbon::now()->subHours(2),
        ],
    ));
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Stayed above threshold since last trigger
    _createAgg($area, '2025-08-13 11:40:00', '2025-08-13 11:50:00', 150);
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 160);

    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();

    // Now a drop below happens, then rise above
    _createAgg($area, '2025-08-13 12:00:00', '2025-08-13 12:10:00', 80);
    _createAgg($area, '2025-08-13 12:10:00', '2025-08-13 12:20:00', 130);

    svc()->processAlertsForArea($area);
    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n) use ($threshold): bool {
        expect($n->configuredThreshold)->toBe($threshold)
            ->and($n->currentOccupancy)->toBe(130);

        return true;
    });
});

it('does nothing when event is not ongoing', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => Carbon::now()->addHour(), // future start
        'ends_at' => Carbon::now()->addHours(3),
    ]);
    $area = Area::factory()->create(['event_id' => $event->id]);
    $u1 = User::factory()->create();
    $org->users()->attach([$u1->id]);

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 10,
        ],
    ));
    $alert->recipients()->attach([$u1->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 999);

    svc()->processAlertsForArea($area);
    Notification::assertNothingSent();
});

it('maps alert channel to notification channels (Vonage)', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Vonage,
        'cooldown_minutes' => 1,
        'occupancy_alert_threshold' => 1,
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 10);

    svc()->processAlertsForArea($area);

    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n): bool {
        expect($n->via($n))->toEqual(['vonage']);

        return true;
    });
});

it('maps alert channel to notification channels (Email)', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 1,
        'occupancy_alert_threshold' => 1,
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 10);

    svc()->processAlertsForArea($area);

    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n) use ($event, $area): bool {
        // Email channel mapping
        expect($n->via($n))->toEqual(['mail']);
        // Sanity-check some mail content pieces
        $mail = $n->toMail((object) []);
        expect($mail->subject)->toContain($area->name)
            ->and($mail->subject)->toContain($event->name);

        return true;
    });
});

it('processSingleAlert no-ops for unknown alert type (edge case)', function () {
    Notification::fake();

    extract(_setupEventAreaAndUsers());

    // Ensure there is a latest aggregated count and event is ongoing
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 999);

    // Create a valid alert but then override its `type` attribute with a dummy object
    // Build a lightweight Alert instance without enum casts
    $alert = new class extends Alert
    {
        protected function casts(): array
        {
            return [];
        }
    };

    // Provide minimal attributes used by the method
    $dummyType = new class
    {
        public string $value = 'unknown_type_value';
    };
    $alert->setRawAttributes([
        'cooldown_minutes' => 0,
        'last_triggered_at' => null,
        'type' => $dummyType,
    ], true);

    // Call protected processSingleAlert via reflection
    $svc = svc();
    $ref = new ReflectionClass($svc);
    $method = $ref->getMethod('processSingleAlert');
    $method->setAccessible(true);

    // Should not throw and should not send any notifications
    $method->invoke($svc, $alert, $area);

    Notification::assertNothingSent();
});

it('evaluateOccupancyAlert returns early when threshold is null (edge case)', function () {
    Notification::fake();

    extract(_setupEventAreaAndUsers());

    // Latest count present and above any reasonable threshold
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 150);

    /** @var Alert $alert */
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        // Leave occupancy_alert_threshold as null to trigger early return
        'occupancy_alert_threshold' => null,
    ]);

    // Call through the public entry to exercise evaluateOccupancyAlert path
    svc()->processAlertsForArea($area);

    // No notification should be sent and last_triggered_at remains null
    Notification::assertNothingSent();
    $alert->refresh();
    expect($alert->last_triggered_at)->toBeNull();
});

it('triggers when current equals the configured threshold', function () {
    Notification::fake();
    extract(_setupEventAreaAndUsers());

    /** @var Alert $alert */
    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(60),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 100,
        ],
    ));
    $alert->recipients()->attach([$u1->id]);

    // Latest equals threshold
    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 100);

    svc()->processAlertsForArea($area);

    Notification::assertSentTo([$u1], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n): bool {
        expect($n->currentOccupancy)->toBe(100)
            ->and($n->configuredThreshold)->toBe(100);

        return true;
    });
});

it('does not trigger when now is exactly at the event end time (end-exclusive window)', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $now = Carbon::now();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => $now->copy()->subHour(),
        'ends_at' => $now->copy(), // exactly now
    ]);
    $area = Area::factory()->create(['event_id' => $event->id]);
    $u1 = User::factory()->create();
    $org->users()->attach([$u1->id]);

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(0),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 10,
        ],
    ));
    $alert->recipients()->attach([$u1->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 999);

    svc()->processAlertsForArea($area);

    Notification::assertNothingSent();
});

it('does trigger when now is exactly at the event start time (start-inclusive window)', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $now = Carbon::now();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'starts_at' => $now->copy(), // exactly now
        'ends_at' => $now->copy()->addHour(),
    ]);
    $area = Area::factory()->create(['event_id' => $event->id]);
    $u1 = User::factory()->create();
    $org->users()->attach([$u1->id]);

    $alert = Alert::factory()->create(array_merge(
        defaultAlertAttrs(0),
        [
            'area_id' => $area->id,
            'occupancy_alert_threshold' => 10,
        ],
    ));
    $alert->recipients()->attach([$u1->id]);

    _createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 50);

    svc()->processAlertsForArea($area);

    Notification::assertSentTo([$u1], AreaOccupancyAlert::class);
});
