<?php

use App\Actions\Peoplecount\UpdateAreaAggregations;
use App\Jobs\AggregateAreaCounts;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\User;
use App\Services\Peoplecount\AlertService;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('updates peoplecount aggregations for global admins', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->mock(UpdateAreaAggregations::class)
        ->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturnTrue();

    $this->actingAs($admin)
        ->patch(route('admin.peoplecount-aggregations.update'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Peoplecount aggregations updated.');
});

it('rebuilds peoplecount aggregations for global admins', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->mock(UpdateAreaAggregations::class)
        ->shouldReceive('handle')
        ->once()
        ->with(true)
        ->andReturnTrue();

    $this->actingAs($admin)
        ->delete(route('admin.peoplecount-aggregations.destroy'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Peoplecount aggregations rebuilt.');
});

it('reports busy status when update aggregation is already running', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->mock(UpdateAreaAggregations::class)
        ->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturnFalse();

    $this->actingAs($admin)
        ->patch(route('admin.peoplecount-aggregations.update'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Peoplecount aggregation is already running.');
});

it('reports busy status when rebuild aggregation is already running', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->mock(UpdateAreaAggregations::class)
        ->shouldReceive('handle')
        ->once()
        ->with(true)
        ->andReturnFalse();

    $this->actingAs($admin)
        ->delete(route('admin.peoplecount-aggregations.destroy'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Peoplecount aggregation is already running.');
});

it('rejects peoplecount aggregation controls without global admin permission', function (string $method, string $route) {
    $user = User::factory()->create();

    $this->mock(UpdateAreaAggregations::class)
        ->shouldNotReceive('handle');

    $this->actingAs($user)
        ->{$method}(route($route))
        ->assertForbidden();
})->with([
    'update' => ['patch', 'admin.peoplecount-aggregations.update'],
    'destroy' => ['delete', 'admin.peoplecount-aggregations.destroy'],
]);

it('does not run while another aggregation holds the lock', function () {
    $lock = Cache::lock(UpdateAreaAggregations::LOCK_KEY, 240);
    $lock->get();

    $this->mock(AreaAggregationService::class)->shouldNotReceive('updateAggregatedCounts');
    $this->mock(AlertService::class)->shouldNotReceive('processAlertsForArea');

    try {
        expect(app(UpdateAreaAggregations::class)->handle())->toBeFalse();
    } finally {
        $lock->release();
    }
});

it('updates every area in one aggregation run', function () {
    $areas = Area::factory()->count(2)->create();
    $aggregatedAreaIds = [];
    $alertAreaIds = [];

    $this->mock(AreaAggregationService::class)
        ->shouldReceive('updateAggregatedCounts')
        ->with(Mockery::type(Area::class))
        ->twice()
        ->andReturnUsing(function (Area $area) use (&$aggregatedAreaIds): void {
            $aggregatedAreaIds[] = $area->id;
        });

    $this->mock(AlertService::class)
        ->shouldReceive('processAlertsForArea')
        ->with(Mockery::type(Area::class))
        ->twice()
        ->andReturnUsing(function (Area $area) use (&$alertAreaIds): void {
            $alertAreaIds[] = $area->id;
        });

    expect(app(UpdateAreaAggregations::class)->handle())->toBeTrue()
        ->and($aggregatedAreaIds)->toEqualCanonicalizing($areas->pluck('id')->all())
        ->and($alertAreaIds)->toEqualCanonicalizing($areas->pluck('id')->all());
});

it('dispatches area aggregation from the scheduler', function () {
    Bus::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Bus::assertDispatched(AggregateAreaCounts::class);
});

it('truncates existing aggregated counts before rebuilding', function () {
    AreaAggregatedCount::factory()->create();

    $this->mock(AreaAggregationService::class)
        ->shouldReceive('updateAggregatedCounts')
        ->with(Mockery::type(Area::class))
        ->once();
    $this->mock(AlertService::class)
        ->shouldReceive('processAlertsForArea')
        ->with(Mockery::type(Area::class))
        ->once();

    expect(app(UpdateAreaAggregations::class)->handle(truncate: true))->toBeTrue()
        ->and(AreaAggregatedCount::query()->count())->toBe(0);
});
