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

function defaultAlertAttrs(?int $cooldownSeconds = 60): array
{
    return [
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_seconds' => $cooldownSeconds,
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
        'cooldown_seconds' => 120,
    ]);

    $svc->updateAreaAlert($graph->org, $graph->area, $alert, [
        'area_id' => $graph->area2->id, // should be ignored/overridden
        'cooldown_seconds' => 240,
        'occupancy_alert_threshold' => 555,
    ]);

    $alert->refresh();
    expect($alert->area_id)->toBe($graph->area->id)
        ->and($alert->cooldown_seconds)->toBe(240)
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
        'cooldown_seconds' => 90,
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
        'cooldown_seconds' => 111,
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
        'cooldown_seconds' => 777,
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
