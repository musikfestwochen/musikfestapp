<?php

declare(strict_types=1);

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Event;
use App\Models\User;
use App\Notifications\Peoplecount\AreaOccupancyAlert;
use App\Services\Peoplecount\AlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // fix time for deterministic assertions
    Carbon::setTestNow(Carbon::parse('2025-08-13 12:00:00')->utc());
});

function setupEventAreaAndUsers(): array
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

    // Create two recipients and attach to org
    $u1 = User::factory()->create(['name' => 'Alert Recipient 1']);
    $u2 = User::factory()->create(['name' => 'Alert Recipient 2']);
    $org->users()->attach([$u1->id, $u2->id]);

    return compact('org', 'event', 'area', 'u1', 'u2');
}

function createAgg(Area $area, string $start, string $end, int $count): AreaAggregatedCount
{
    return AreaAggregatedCount::factory()
        ->withArea($area)
        ->withPeriod(Carbon::parse($start)->utc(), Carbon::parse($end)->utc())
        ->create([
            'count' => $count,
        ]);
}

it('sends occupancy alert when latest aggregated count meets threshold', function () {
    Notification::fake();

    extract(setupEventAreaAndUsers());

    // Configure alert
    /** @var Alert $alert */
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 60,
        'occupancy_alert_threshold' => 100,
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Create aggregated counts (latest above threshold)
    createAgg($area, '2025-08-13 11:40:00', '2025-08-13 11:50:00', 90);
    createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 120);

    app(AlertService::class)->processAlertsForArea($area);

    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n) use ($event, $area): bool {
        expect($n->eventName)->toBe($event->name)
            ->and($n->areaName)->toBe($area->name)
            ->and($n->configuredThreshold)->toBe(100)
            ->and($n->currentOccupancy)->toBe(120);

        return true;
    });

    $alert->refresh();
    expect($alert->last_triggered_at)->not()->toBeNull()
        ->and($alert->last_triggered_at->equalTo(Carbon::now()))->toBeTrue();
});

it('does not send when latest aggregated count is below threshold', function () {
    Notification::fake();

    extract(setupEventAreaAndUsers());

    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 60,
        'occupancy_alert_threshold' => 200,
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Latest below threshold
    createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 150);

    app(AlertService::class)->processAlertsForArea($area);

    Notification::assertNothingSent();
    $alert->refresh();
    expect($alert->last_triggered_at)->toBeNull();
});

it('respects cooldown and does not re-send within cooldown window', function () {
    Notification::fake();

    extract(setupEventAreaAndUsers());

    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 60,
        'occupancy_alert_threshold' => 50,
        'last_triggered_at' => Carbon::now()->subMinutes(30), // still in cooldown
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // Latest above threshold but within cooldown
    createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 300);

    app(AlertService::class)->processAlertsForArea($area);

    Notification::assertNothingSent();
});

it('requires a drop below threshold since last trigger before re-sending', function () {
    Notification::fake();

    extract(setupEventAreaAndUsers());

    $threshold = 100;

    // Last triggered long enough ago (no cooldown), but we will not drop below first
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 10,
        'occupancy_alert_threshold' => $threshold,
        'last_triggered_at' => Carbon::now()->subHours(2),
    ]);
    $alert->recipients()->attach([$u1->id, $u2->id]);

    // After last trigger, counts remained above threshold
    createAgg($area, '2025-08-13 11:40:00', '2025-08-13 11:50:00', 150);
    createAgg($area, '2025-08-13 11:50:00', '2025-08-13 12:00:00', 160);

    app(AlertService::class)->processAlertsForArea($area);

    // No send because there was no drop below since last trigger
    Notification::assertNothingSent();

    // Now simulate a drop below threshold after last trigger, then back above
    createAgg($area, '2025-08-13 12:00:00', '2025-08-13 12:10:00', 80);   // drop below
    createAgg($area, '2025-08-13 12:10:00', '2025-08-13 12:20:00', 130); // back above (latest)

    app(AlertService::class)->processAlertsForArea($area);

    Notification::assertSentTo([$u1, $u2], AreaOccupancyAlert::class, function (AreaOccupancyAlert $n) use ($threshold): bool {
        expect($n->configuredThreshold)->toBe($threshold)
            ->and($n->currentOccupancy)->toBe(130);

        return true;
    });
});

it('formats mail and vonage messages correctly for AreaOccupancyAlert', function () {
    $n = new AreaOccupancyAlert(
        eventName: 'Event X',
        areaName: 'Area Y',
        currentOccupancy: 123,
        configuredThreshold: 100,
    );

    $mail = $n->toMail(new stdClass);
    expect($mail->subject)->toBe('Occupancy alert: Area Y @ Event X')
        ->and($mail->greeting)->toBe('Threshold Exceeded')
        ->and($mail->introLines)->toBe([
            'Event: Event X',
            'Area: Area Y',
            'Current occupancy: 123',
            'Configured threshold: 100',
        ]);

    $sms = $n->toVonage(new stdClass);
    expect($sms->content)->toBe('Occupancy alert - Event X / Area Y: 123 (threshold 100).');
});
