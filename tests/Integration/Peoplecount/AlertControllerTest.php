<?php

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Http\Controllers\Peoplecount\AlertController;
use App\Http\Requests\Peoplecount\AlertCreateRequest;
use App\Http\Requests\Peoplecount\AlertDestroyRequest;
use App\Http\Requests\Peoplecount\AlertEditRequest;
use App\Http\Requests\Peoplecount\AlertShowRequest;
use App\Http\Requests\Peoplecount\AlertStoreRequest;
use App\Http\Requests\Peoplecount\AlertUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

function setupOrgEventAreaWithUsers(): array
{
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
    ]);
    $area = Area::factory()->create([
        'event_id' => $event->id,
    ]);

    // Create org members and an outsider
    $u1 = User::factory()->create(['name' => 'Alice Alert']);
    $u2 = User::factory()->create(['name' => 'Bob Alert']);
    $uOut = User::factory()->create(['name' => 'Mallory']);
    $u1->organizations()->attach($org->id);
    $u2->organizations()->attach($org->id);
    $otherOrg = Organization::factory()->create();
    $uOut->organizations()->attach($otherOrg->id);

    return compact('admin', 'org', 'event', 'area', 'u1', 'u2', 'uOut');
}

it('shows the create alert form with org users', function () {
    extract(setupOrgEventAreaWithUsers());

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.alerts.create', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/NewAlert', false)
            ->where('organization.id', $org->id)
            ->where('area.id', $area->id)
            ->has('users', 2) // only org members
            ->has('users.0', fn (Assert $p): Assert => $p->has('id')->has('name')->has('email'))
            ->has('status')
        );
});

it('can store a new alert with recipients and redirects back to area edit', function () {
    extract(setupOrgEventAreaWithUsers());

    $payload = [
        'type' => AlertType::OccupancyAlert->value,
        'channel' => AlertChannel::Email->value,
        'cooldown_minutes' => '60',
        'occupancy_alert_threshold' => '123',
        'recipients' => [(string) $u1->id, (string) $u2->id],
    ];

    $response = $this->actingAs($admin)
        ->post(route('peoplecount.areas.alerts.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $payload);

    $response->assertRedirect(route('peoplecount.areas.edit', [
        'organization' => $org->slug,
        'area' => $area->id,
    ]));

    $this->assertDatabaseHas('peoplecount_alerts', [
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert->value,
        'channel' => AlertChannel::Email->value,
        'cooldown_minutes' => 60,
        'occupancy_alert_threshold' => 123,
    ]);

    /** @var Alert $alert */
    $alert = Alert::query()->where('area_id', $area->id)->first();
    expect($alert)->not()->toBeNull()
        ->and($alert->recipients)->toHaveCount(2);
});

it('rejects recipients outside the organization on store', function () {
    extract(setupOrgEventAreaWithUsers());

    $payload = [
        'type' => AlertType::OccupancyAlert->value,
        'channel' => AlertChannel::Vonage->value,
        'cooldown_minutes' => 30,
        'occupancy_alert_threshold' => 10,
        'recipients' => [$u1->id, $uOut->id],
    ];

    $this->actingAs($admin)
        ->post(route('peoplecount.areas.alerts.store', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]), $payload)
        ->assertForbidden();
});

it('redirects show to edit for nested alert', function () {
    extract(setupOrgEventAreaWithUsers());
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
        'created_by' => $admin->id,
        'occupancy_alert_threshold' => 55,
    ]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.alerts.show', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]))
        ->assertRedirect(route('peoplecount.areas.alerts.edit', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]));
});

it('shows the edit alert form with users and recipients loaded', function () {
    extract(setupOrgEventAreaWithUsers());
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
        'created_by' => $admin->id,
        'occupancy_alert_threshold' => 55,
    ]);
    // Attach one recipient
    $alert->recipients()->attach([$u1->id]);

    $this->actingAs($admin)
        ->get(route('peoplecount.areas.alerts.edit', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('peoplecount/EditAlert')
            ->where('organization.id', $org->id)
            ->where('area.id', $area->id)
            ->has('alert', fn (Assert $p): Assert => $p
                ->where('id', $alert->id)
                ->has('recipients', 1)
                ->etc()
            )
            ->has('users', 2)
            ->has('status')
        );
});

it('can update an existing alert including recipients', function () {
    extract(setupOrgEventAreaWithUsers());
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
        'created_by' => $admin->id,
        'occupancy_alert_threshold' => 55,
    ]);

    $payload = [
        'type' => AlertType::OccupancyAlert->value,
        'channel' => AlertChannel::Vonage->value,
        'cooldown_minutes' => '600',
        'occupancy_alert_threshold' => '500',
        'recipients' => [(string) $u2->id],
    ];

    $this->actingAs($admin)
        ->put(route('peoplecount.areas.alerts.update', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]), $payload)
        ->assertRedirect(route('peoplecount.areas.edit', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]));

    $alert->refresh();
    expect($alert->channel)->toBe(AlertChannel::Vonage)
        ->and($alert->cooldown_minutes)->toBe(600)
        ->and($alert->occupancy_alert_threshold)->toBe(500)
        ->and($alert->recipients()->pluck('users.id')->all())->toBe([$u2->id]);
});

it('rejects recipients outside the organization on update', function () {
    extract(setupOrgEventAreaWithUsers());
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
        'created_by' => $admin->id,
        'occupancy_alert_threshold' => 55,
    ]);

    $payload = [
        'type' => AlertType::OccupancyAlert->value,
        'channel' => AlertChannel::Email->value,
        'cooldown_minutes' => 100,
        'occupancy_alert_threshold' => 20,
        'recipients' => [$uOut->id],
    ];

    $this->actingAs($admin)
        ->put(route('peoplecount.areas.alerts.update', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]), $payload)
        ->assertForbidden();
});

it('can destroy an alert and redirect back to area edit', function () {
    extract(setupOrgEventAreaWithUsers());
    $alert = Alert::factory()->create([
        'area_id' => $area->id,
        'type' => AlertType::OccupancyAlert,
        'channel' => AlertChannel::Email,
        'cooldown_minutes' => 120,
        'created_by' => $admin->id,
        'occupancy_alert_threshold' => 55,
    ]);

    $this->actingAs($admin)
        ->delete(route('peoplecount.areas.alerts.destroy', [
            'organization' => $org->slug,
            'area' => $area->id,
            'alert' => $alert->id,
        ]))
        ->assertRedirect(route('peoplecount.areas.edit', [
            'organization' => $org->slug,
            'area' => $area->id,
        ]));

    $this->assertDatabaseMissing('peoplecount_alerts', [
        'id' => $alert->id,
    ]);
});

it('uses the correct form requests and middleware for nested resource', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.areas.alerts.create',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'create',
        AlertCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.create',
        AlertCreateRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'edit',
        AlertEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.edit',
        AlertEditRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'show',
        AlertShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.show',
        AlertShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'store',
        AlertStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.store',
        AlertStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'update',
        AlertUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.update',
        AlertUpdateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        AlertController::class,
        'destroy',
        AlertDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'peoplecount.areas.alerts.destroy',
        AlertDestroyRequest::class);
});
