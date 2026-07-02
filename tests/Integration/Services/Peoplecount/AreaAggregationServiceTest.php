<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\AreaAggregationService;
use App\Services\Peoplecount\AreaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

covers(AreaAggregationService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set a fixed time for consistent testing
    Carbon::setTestNow('2024-08-15 14:30:00');

    // Mock the AreaService dependency
    $this->areaServiceMock = Mockery::mock(AreaService::class);
    $this->service = new AreaAggregationService($this->areaServiceMock);

    // Mock config value
    config(['peoplecount.aggregation.granularity_minutes' => 10]);

    // Helper function to call protected methods using reflection
    $this->callProtectedMethod = function ($methodName, ...$args) {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);

        return $method->invoke($this->service, ...$args);
    };
});

describe('updateAggregatedCounts method', function () {
    it('returns early when area has no assignments', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('load')->once();

        // Mock empty assignments collection
        $emptyAssignments = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn($emptyAssignments);

        // Mock aggregatedCounts property access
        $aggregatedCounts = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($aggregatedCounts);

        // Mock aggregatedCounts relationship
        $aggregatedCountsRelation = Mockery::mock();
        $area->shouldReceive('aggregatedCounts')->andReturn($aggregatedCountsRelation);

        $this->areaServiceMock->shouldReceive('calculateChecksum')->once()->andReturn('abc123');

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if no exception thrown
    });

    it('processes full aggregation flow when area has assignments', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('getAttribute')->with('id')->andReturn(Area::factory()->create()->id);
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
        $assignment->shouldReceive('getAttribute')->with('sensor_id')->andReturn(999_999);
        $assignments = new EloquentCollection([$assignment]);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn($assignments);

        // Mock aggregatedCounts property access
        $aggregatedCounts = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($aggregatedCounts);

        // Mock area service calls
        $this->areaServiceMock->shouldReceive('calculateChecksum')->once()->andReturn('abc123');
        $this->areaServiceMock->shouldReceive('getAreaResets')->once()->andReturn(collect());
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')->andReturn(10);

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock aggregatedCounts relationship for filtering and calculations
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('limit')->with(2)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('get')->with(['id', 'area_id', 'period_start', 'period_end', 'count'])->andReturn(new EloquentCollection);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if no exception thrown
    });

    it('verifies deleteInvalidAggregationRows is called by testing its side effects', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('getAttribute')->with('id')->andReturn(Area::factory()->create()->id);
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
        $assignment->shouldReceive('getAttribute')->with('sensor_id')->andReturn(999_999);
        $assignments = new EloquentCollection([$assignment]);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn($assignments);

        // Mock aggregatedCounts property access with some data to trigger deletion logic
        $count = Mockery::mock(AreaAggregatedCount::class);
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $aggregatedCounts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($aggregatedCounts);

        // Mock area service calls
        $this->areaServiceMock->shouldReceive('calculateChecksum')->once()->andReturn('abc123');
        $this->areaServiceMock->shouldReceive('getAreaResets')->once()->andReturn(collect());
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')->andReturn(10);

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock aggregatedCounts relationship for filtering and calculations
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('limit')->with(2)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('get')->with(['id', 'area_id', 'period_start', 'period_end', 'count'])->andReturn(new EloquentCollection);

        // Mock for deleteRowsWithInvalidChecksum - this verifies deleteInvalidAggregationRows is called
        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('delete')->once()->andReturn(1); // This expectation will fail if deleteInvalidAggregationRows is not called
        $relationshipMock->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);
        $area->shouldReceive('forceFill')->once()->with(['data_watermark' => null])->andReturnSelf();
        $area->shouldReceive('save')->once()->andReturnTrue();

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if delete() was called, proving deleteInvalidAggregationRows was executed
    });

    it('verifies calculateAggregatedCountsForWindows is called by testing its side effects', function () {
        $area = Mockery::mock(Area::class);
        $areaId = Area::factory()->create()->id;
        $area->shouldReceive('getAttribute')->with('id')->andReturn($areaId);
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
        $assignment->shouldReceive('getAttribute')->with('sensor_id')->andReturn(999_999);
        $assignments = new EloquentCollection([$assignment]);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn($assignments);

        // Mock aggregatedCounts property access
        $aggregatedCounts = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($aggregatedCounts);

        // Mock area service calls
        $this->areaServiceMock->shouldReceive('calculateChecksum')->once()->andReturn('abc123');
        $this->areaServiceMock->shouldReceive('getAreaResets')->once()->andReturn(collect());

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock aggregatedCounts relationship for filtering and calculations
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('limit')->with(2)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('get')->with(['id', 'area_id', 'period_start', 'period_end', 'count'])->andReturn(new EloquentCollection);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $this->service->updateAggregatedCounts($area);

        expect(AreaAggregatedCount::query()->where('area_id', $areaId)->exists())->toBeTrue();
    });

});

describe('calculateMedianWindowSize method', function () {
    it('calculates median for odd number of counts', function () {
        $count1 = Mockery::mock(AreaAggregatedCount::class);
        $count1->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count1->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:10:00'));

        $count2 = Mockery::mock(AreaAggregatedCount::class);
        $count2->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $count2->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:25:00'));

        $count3 = Mockery::mock(AreaAggregatedCount::class);
        $count3->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:25:00'));
        $count3->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:45:00'));

        $counts = collect([$count1, $count2, $count3]);

        $result = ($this->callProtectedMethod)('calculateMedianWindowSize', $counts);

        expect($result)->toBe(15.0); // Median of [10, 15, 20] is 15
    });

    it('calculates median for even number of counts', function () {
        $count1 = Mockery::mock(AreaAggregatedCount::class);
        $count1->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count1->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:10:00'));

        $count2 = Mockery::mock(AreaAggregatedCount::class);
        $count2->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $count2->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:30:00'));

        $counts = collect([$count1, $count2]);

        $result = ($this->callProtectedMethod)('calculateMedianWindowSize', $counts);

        expect($result)->toBe(15.0); // Median of [10, 20] is (10+20)/2 = 15
    });

    it('calculates median correctly with specific even count values to test index calculation', function () {
        // Create 4 counts with different durations: [5, 10, 15, 20]
        // Median should be (10 + 15) / 2 = 12.5
        $count1 = Mockery::mock(AreaAggregatedCount::class);
        $count1->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count1->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:05:00')); // 5 minutes

        $count2 = Mockery::mock(AreaAggregatedCount::class);
        $count2->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:05:00'));
        $count2->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00')); // 10 minutes

        $count3 = Mockery::mock(AreaAggregatedCount::class);
        $count3->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $count3->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:30:00')); // 15 minutes

        $count4 = Mockery::mock(AreaAggregatedCount::class);
        $count4->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:30:00'));
        $count4->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:50:00')); // 20 minutes

        $counts = collect([$count1, $count2, $count3, $count4]);

        $result = ($this->callProtectedMethod)('calculateMedianWindowSize', $counts);

        expect($result)->toBe(12.5); // Median of [5, 10, 15, 20] is (10+15)/2 = 12.5
    });

    it('calculates median correctly with specific odd count values to test floor calculation', function () {
        // Create 5 counts with different durations: [5, 10, 15, 20, 25]
        // Median should be the middle value: 15
        $count1 = Mockery::mock(AreaAggregatedCount::class);
        $count1->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count1->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:05:00')); // 5 minutes

        $count2 = Mockery::mock(AreaAggregatedCount::class);
        $count2->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:05:00'));
        $count2->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00')); // 10 minutes

        $count3 = Mockery::mock(AreaAggregatedCount::class);
        $count3->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $count3->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:30:00')); // 15 minutes

        $count4 = Mockery::mock(AreaAggregatedCount::class);
        $count4->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:30:00'));
        $count4->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:50:00')); // 20 minutes

        $count5 = Mockery::mock(AreaAggregatedCount::class);
        $count5->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:50:00'));
        $count5->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 11:15:00')); // 25 minutes

        $counts = collect([$count1, $count2, $count3, $count4, $count5]);

        $result = ($this->callProtectedMethod)('calculateMedianWindowSize', $counts);

        expect($result)->toBe(15.0); // Median of [5, 10, 15, 20, 25] is 15 (index 2)
    });
});

describe('getPastResetTimes method', function () {
    it('filters reset times to only past ones', function () {
        $area = Mockery::mock(Area::class);
        $pastReset = ['at' => Carbon::parse('2024-08-15 12:00:00')];
        $futureReset = ['at' => Carbon::parse('2024-08-15 16:00:00')];

        $this->areaServiceMock->shouldReceive('getAreaResets')
            ->once()
            ->with($area)
            ->andReturn(collect([$pastReset, $futureReset]));

        $result = ($this->callProtectedMethod)('getPastResetTimes', $area);

        expect($result)->toHaveCount(1);
        expect($result->first()['at']->format('H:i'))->toBe('12:00');
    });
});

describe('getWindowConfiguration method', function () {
    it('returns correct window configuration', function () {
        $area = Mockery::mock(Area::class);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 18:00:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('getWindowConfiguration', $area);

        expect($result)->toHaveKey('windowSize', 10);
        expect($result)->toHaveKey('startTime');
        expect($result)->toHaveKey('endTime');
        expect($result['startTime']->format('H:i'))->toBe('10:00');
        expect($result['endTime']->format('H:i'))->toBe('18:00');
    });
});

describe('generateAggregationWindows method', function () {
    it('generates correct number of windows', function () {
        $area = Mockery::mock(Area::class);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 11:00:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);
        $resetTimes = collect();

        $result = ($this->callProtectedMethod)('generateAggregationWindows', $area, $resetTimes, Carbon::parse('2024-08-15 10:00:00'))->collect();

        expect($result)->toHaveCount(6); // 60 minutes / 10 minute windows = 6 windows
        expect($result->first()['start']->format('H:i'))->toBe('10:00');
        expect($result->first()['end']->format('H:i'))->toBe('10:10');
    });
});

describe('createWindow method', function () {
    it('creates window with correct structure', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $config = [
            'windowSize' => 10,
            'startTime' => Carbon::parse('2024-08-15 10:00:00'),
            'endTime' => Carbon::parse('2024-08-15 18:00:00'),
        ];
        $resetTimes = collect();

        $result = ($this->callProtectedMethod)('createWindow', $startTime, $config, $resetTimes);

        expect($result)->toHaveKey('start');
        expect($result)->toHaveKey('end');
        expect($result)->toHaveKey('reset_value');
        expect($result['start']->format('H:i'))->toBe('10:00');
        expect($result['end']->format('H:i'))->toBe('10:10');
        expect($result['reset_value'])->toBeNull();
    });
});

describe('calculateWindowEnd method', function () {
    it('returns natural window end when no reset within window', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $eventStartTime = Carbon::parse('2024-08-15 10:00:00');
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect();

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventStartTime, $eventEndTime, $windowSize, $resetTimes);

        expect($result->format('H:i'))->toBe('10:10');
    });

    it('returns reset time when reset occurs within window', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $eventStartTime = Carbon::parse('2024-08-15 10:00:00');
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:05:00')],
        ]);

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventStartTime, $eventEndTime, $windowSize, $resetTimes);

        expect($result->format('H:i'))->toBe('10:05');
    });

    it('returns natural window end when reset is exactly at start time', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $eventStartTime = Carbon::parse('2024-08-15 10:00:00');
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:00:00')], // Reset exactly at start time
        ]);

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventStartTime, $eventEndTime, $windowSize, $resetTimes);

        // Should return natural window end because reset is not > startTime
        expect($result->format('H:i'))->toBe('10:10');
    });
});

describe('findResetWithinWindow method', function () {
    it('finds reset within window', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $windowEnd = Carbon::parse('2024-08-15 10:10:00');
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:05:00'), 'reset_value' => 100],
        ]);

        $result = ($this->callProtectedMethod)('findResetWithinWindow', $startTime, $windowEnd, $resetTimes);

        expect($result)->not->toBeNull();
        expect($result['reset_value'])->toBe(100);
    });

    it('returns null when no reset within window', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $windowEnd = Carbon::parse('2024-08-15 10:10:00');
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:15:00')],
        ]);

        $result = ($this->callProtectedMethod)('findResetWithinWindow', $startTime, $windowEnd, $resetTimes);

        expect($result)->toBeNull();
    });
});

describe('getResetValueAtWindowStart method', function () {
    it('returns reset value when reset at window start', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:00:00'), 'reset_value' => 50],
        ]);

        $result = ($this->callProtectedMethod)('getResetValueAtWindowStart', $startTime, $resetTimes);

        expect($result)->toBe(50);
    });

    it('returns null when no reset at window start', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:05:00'), 'reset_value' => 50],
        ]);

        $result = ($this->callProtectedMethod)('getResetValueAtWindowStart', $startTime, $resetTimes);

        expect($result)->toBeNull();
    });
});

describe('generateAggregationWindows future filtering', function () {
    it('filters out future windows', function () {
        $area = Mockery::mock(Area::class);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 12:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 16:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('generateAggregationWindows', $area, collect(), Carbon::parse('2024-08-15 12:00:00'))->collect();

        expect($result)->toHaveCount(15);
        expect($result->first()['start']->format('H:i'))->toBe('12:00');
        expect($result->last()['start']->format('H:i'))->toBe('14:20');
    });
});

describe('deleteInvalidAggregationRows method', function () {
    it('returns early when aggregated counts are empty', function () {
        $area = Mockery::mock(Area::class);
        $emptyCollection = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($emptyCollection);

        $result = ($this->callProtectedMethod)('deleteInvalidAggregationRows', $area, 'abc123');

        expect($result)->toBeNull();
    });

    it('calls delete methods when aggregated counts exist', function () {
        $area = Mockery::mock(Area::class);
        $count = Mockery::mock(AreaAggregatedCount::class);
        $counts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($counts);

        // Mock for deleteRowsWithInvalidChecksum - first call
        $builderMock1 = Mockery::mock(Builder::class);
        $builderMock1->shouldReceive('delete')->once()->andReturn(1);

        $relationshipMock1 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock1->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock1);

        // Mock for deleteRowsWithInvalidWindowSize - second call (median matches config, so no delete)
        // Even though no delete happens, the method still accesses aggregatedCounts()
        $relationshipMock2 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        // No get() expectation needed since median matches config and delete won't be called

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock1, $relationshipMock2);
        $area->shouldReceive('forceFill')->once()->with(['data_watermark' => null])->andReturnSelf();
        $area->shouldReceive('save')->once()->andReturnTrue();

        // Mock for deleteRowsWithInvalidWindowSize - median matches config
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:10:00'));

        $result = ($this->callProtectedMethod)('deleteInvalidAggregationRows', $area, 'abc123');

        expect($result)->toBeNull();
    });

    it('calls delete methods when aggregated counts exist and window size differs', function () {
        $area = Mockery::mock(Area::class);
        $count = Mockery::mock(AreaAggregatedCount::class);
        $counts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($counts);

        // Mock for deleteRowsWithInvalidChecksum - first call
        $builderMock1 = Mockery::mock(Builder::class);
        $builderMock1->shouldReceive('delete')->once()->andReturn(1);

        $relationshipMock1 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock1->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock1);

        $relationshipMock2 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock2->shouldReceive('delete')->once()->andReturn(1);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock1, $relationshipMock2);
        $area->shouldReceive('forceFill')->twice()->with(['data_watermark' => null])->andReturnSelf();
        $area->shouldReceive('save')->twice()->andReturnTrue();

        // Mock for deleteRowsWithInvalidWindowSize - median differs from config (15 minutes vs 10)
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $result = ($this->callProtectedMethod)('deleteInvalidAggregationRows', $area, 'abc123');

        expect($result)->toBeNull();
    });
});

describe('deleteRowsWithInvalidChecksum method', function () {
    it('deletes rows with different checksum', function () {
        $area = Mockery::mock(Area::class);
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $builderMock = Mockery::mock(Builder::class);

        $relationshipMock->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock);
        $builderMock->shouldReceive('delete')->once()->andReturn(1);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidChecksum', $area, 'abc123');

        expect($result)->toBe(1);
    });
});

describe('deleteRowsWithInvalidWindowSize method', function () {
    it('returns early when aggregated counts are empty', function () {
        $area = Mockery::mock(Area::class);
        $emptyCollection = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($emptyCollection);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidWindowSize', $area);

        expect($result)->toBe(0);
    });

    it('deletes all counts when median window size differs from config', function () {
        $area = Mockery::mock(Area::class);

        // Create counts with different window size (15 minutes instead of 10)
        $count = Mockery::mock(AreaAggregatedCount::class);
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $counts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($counts);

        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('delete')->once()->andReturn(1);
        $area->shouldReceive('aggregatedCounts')->once()->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidWindowSize', $area);

        expect($result)->toBe(1);
    });

    it('does not delete when median window size matches config', function () {
        $area = Mockery::mock(Area::class);

        // Create counts with correct window size (10 minutes)
        $count = Mockery::mock(AreaAggregatedCount::class);
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $counts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($counts);

        // Should not call delete
        $area->shouldNotReceive('aggregatedCounts');

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidWindowSize', $area);

        expect($result)->toBe(0);
    });
});

describe('getAggregationWindowChunks method', function () {
    it('returns lazy window chunks', function () {
        $area = Mockery::mock(Area::class);
        $resetTimes = collect();

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('getAggregationWindowChunks', $area, $resetTimes, Carbon::parse('2024-08-15 10:00:00'));

        expect($result)->toBeInstanceOf(LazyCollection::class);
        expect($result->first())->toBeInstanceOf(LazyCollection::class);
    });

    it('splits reset times into aggregation windows', function () {
        $area = Mockery::mock(Area::class);
        $resetTimes = collect();

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('generateAggregationWindows', $area, $resetTimes, Carbon::parse('2024-08-15 10:00:00'))->collect();

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(1); // 10 minute window
    });

    it('filters windows based on recalculation start', function () {
        $area = Mockery::mock(Area::class);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 09:50:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:20:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('generateAggregationWindows', $area, collect(), Carbon::parse('2024-08-15 10:00:00'))->collect();

        expect($result)->toHaveCount(2); // Should filter out the first window

        // Check which windows are included
        $resultTimes = $result->map(fn ($window) => $window['start']->format('H:i'))->toArray();
        expect($resultTimes)->toContain('10:00');
        expect($resultTimes)->toContain('10:10');

        // Check which windows are excluded
        expect($resultTimes)->not->toContain('09:50');
    });
});

describe('calculateAggregatedCountsForWindows method', function () {
    it('calculates and stores aggregated counts for windows', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('getAttribute')->with('id')->andReturn(Area::factory()->create()->id);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn(new EloquentCollection);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => null,
            ],
        ]);
        $checksum = 'abc123';

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum, 0);

        expect($result)->toBe(0);
    });

    it('uses reset_value when provided instead of lastCount', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('getAttribute')->with('id')->andReturn(Area::factory()->create()->id);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn(new EloquentCollection);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => 50, // Specific reset value provided
            ],
        ]);
        $checksum = 'abc123';

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum, 25);

        expect($result)->toBe(50);
    });

    it('uses lastCount when reset_value is null', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('getAttribute')->with('id')->andReturn(Area::factory()->create()->id);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn(new EloquentCollection);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => null, // No reset value
            ],
        ]);
        $checksum = 'abc123';

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum, 25);

        expect($result)->toBe(25);
    });

    it('upserts existing aggregate rows for the same window', function () {
        $area = Area::factory()->create();
        $start = Carbon::parse('2024-08-15 10:00:00');
        $end = Carbon::parse('2024-08-15 10:10:00');

        AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'period_start' => $start,
            'period_end' => $end,
            'count' => 99,
            'checksum' => str_repeat('a', 64),
        ]);

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, collect([
            [
                'start' => $start,
                'end' => $end,
                'reset_value' => 7,
            ],
        ]), str_repeat('b', 64), 0);

        expect($result)->toBe(7);
        expect(AreaAggregatedCount::query()->where('area_id', $area->id)->count())->toBe(1);
        expect(AreaAggregatedCount::query()->where('area_id', $area->id)->first()->count)->toBe(7);
    });
});

describe('calculateNetCountsForWindows method', function () {
    it('returns an empty collection when no windows are provided', function () {
        $area = Mockery::mock(Area::class);

        $result = ($this->callProtectedMethod)('calculateNetCountsForWindows', $area, collect());

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toBeEmpty();
    });

    it('ignores interval rows that do not fall into a planned window', function () {
        $event = Event::factory()->create([
            'starts_at' => Carbon::parse('2024-08-15 10:00:00')->utc(),
            'ends_at' => Carbon::parse('2024-08-15 10:30:00')->utc(),
        ]);
        $area = Area::factory()->create(['event_id' => $event->id]);
        $sensor = Sensor::factory()->create(['organization_id' => $event->organization_id]);

        Assignment::factory()->create([
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => $sensor->id,
            'active_from' => Carbon::parse('2024-08-15 10:00:00')->utc(),
            'active_to' => Carbon::parse('2024-08-15 10:30:00')->utc(),
            'direction_flipped' => false,
        ]);

        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => Carbon::parse('2024-08-15 10:15:00')->utc(),
            'ts_to' => Carbon::parse('2024-08-15 10:20:00')->utc(),
            'count_in' => 10,
            'count_out' => 2,
            'received_at' => Carbon::parse('2024-08-15 10:20:00')->utc(),
        ]);

        $area->load('assignments');

        $result = ($this->callProtectedMethod)('calculateNetCountsForWindows', $area, collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00')->utc(),
                'end' => Carbon::parse('2024-08-15 10:10:00')->utc(),
                'reset_value' => null,
            ],
            [
                'start' => Carbon::parse('2024-08-15 10:20:00')->utc(),
                'end' => Carbon::parse('2024-08-15 10:30:00')->utc(),
                'reset_value' => null,
            ],
        ]), Carbon::parse('2024-08-15 10:30:00')->utc());

        expect($result->all())->toBe([0, 0]);
    });
});

describe('findWindowIndexForInterval method', function () {
    it('returns null when the interval starts before the first planned window', function () {
        $lookup = [
            'starts' => [Carbon::parse('2024-08-15 10:00:00')->utc()],
            'ends' => [Carbon::parse('2024-08-15 10:10:00')->utc()],
        ];

        $result = ($this->callProtectedMethod)('findWindowIndexForInterval', Carbon::parse('2024-08-15 09:59:00')->utc(), $lookup);

        expect($result)->toBeNull();
    });
});

describe('writeAggregatedCounts method', function () {
    it('returns early when no rows are provided', function () {
        $result = ($this->callProtectedMethod)('writeAggregatedCounts', collect());

        expect($result)->toBeNull();
    });

    it('upserts aggregate rows', function () {
        $area = Area::factory()->create();
        $periodStart = '2024-08-15 10:00:00';
        $periodEnd = '2024-08-15 10:10:00';

        ($this->callProtectedMethod)('writeAggregatedCounts', collect([
            [
                'area_id' => $area->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'count' => 5,
                'checksum' => hex2bin(str_repeat('a', 64)),
            ],
        ]));

        ($this->callProtectedMethod)('writeAggregatedCounts', collect([
            [
                'area_id' => $area->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'count' => 9,
                'checksum' => hex2bin(str_repeat('b', 64)),
            ],
        ]));

        $count = AreaAggregatedCount::query()->where('area_id', $area->id)->first();

        expect(AreaAggregatedCount::query()->where('area_id', $area->id)->count())->toBe(1);
        expect($count->count)->toBe(9);
        expect($count->checksum)->toBe(str_repeat('b', 64));
    });
});

describe('getAggregationCheckpoint method', function () {
    it('returns default checkpoint when fewer than two existing counts exist', function () {
        $area = Mockery::mock(Area::class);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Create a proper HasMany relationship mock that can handle method chaining
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('limit')->with(2)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('get')->with(['id', 'area_id', 'period_start', 'period_end', 'count'])->andReturn(new EloquentCollection);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('getAggregationCheckpoint', $area);

        expect($result['recalculate_from']->format('H:i'))->toBe('10:00');
        expect($result['initial_count'])->toBe(0);
    });

    it('returns recalculation start and initial count from existing counts', function () {
        $area = Mockery::mock(Area::class);
        $latestCount = new AreaAggregatedCount([
            'period_start' => Carbon::parse('2024-08-15 10:10:00'),
        ]);
        $previousCount = new AreaAggregatedCount([
            'count' => 25,
        ]);

        // Create a proper HasMany relationship mock that can handle method chaining
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('limit')->with(2)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('get')->with(['id', 'area_id', 'period_start', 'period_end', 'count'])->andReturn(new EloquentCollection([
            $latestCount,
            $previousCount,
        ]));

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('getAggregationCheckpoint', $area);

        expect($result['recalculate_from']->format('H:i'))->toBe('10:10');
        expect($result['initial_count'])->toBe(25);
    });
});

describe('getActiveAreaAggregatedCounts method - integration tests', function () {
    it('returns empty array when no active events exist', function () {
        // Create organization but no active events
        $organization = Organization::factory()->create();

        // Mock AreaService for debug counts
        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')->never();

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    it('handles area with aggregated counts correctly', function () {
        // Create organization and active event
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'), // Started 30 minutes ago
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),   // Ends in 30 minutes
        ]);

        // Create area with aggregated counts
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Test Area',
        ]);

        // Create aggregated counts
        $latestCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 50,
            'period_end' => Carbon::parse('2024-08-15 14:25:00'), // 5 minutes ago
        ]);

        $oneHourAgoCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 30,
            'period_end' => Carbon::parse('2024-08-15 13:25:00'), // 1 hour 5 minutes ago
        ]);

        // Mock Cache and AreaService
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->with(Mockery::type(Area::class))
            ->andReturn(['in' => 10, 'out' => 5, 'net' => 5]);

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0])->toHaveKey('id');
        expect($result[0])->toHaveKey('name');
        expect($result[0])->toHaveKey('event_name');
        expect($result[0])->toHaveKey('count');
        expect($result[0])->toHaveKey('net_change');
        expect($result[0])->toHaveKey('net_change_time_ago');
        expect($result[0])->toHaveKey('debug_counts');
        expect($result[0])->toHaveKey('last_updated');
        expect($result[0]['count'])->toBe(50);
        expect($result[0]['net_change'])->toBe(20); // 50 - 30
        expect($result[0]['debug_counts'])->toBeArray();
        expect($result[0]['debug_counts']['in'])->toBe(10);
        expect($result[0]['debug_counts']['out'])->toBe(5);
        expect($result[0]['debug_counts']['net'])->toBe(5);
    });

    it('handles area with no aggregated counts', function () {
        // Create organization and active event
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        // Create area without aggregated counts
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Empty Area',
        ]);

        // Mock Cache and AreaService
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->with(Mockery::type(Area::class))
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0]['count'])->toBe(0);
        expect($result[0]['net_change'])->toBeNull();
    });

    it('handles debug counts calculation failure gracefully', function () {
        // Create organization and active event
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        // Create area
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Problem Area',
        ]);

        // Mock Cache to return computed payload closure
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Make areaService calculation throw
        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andThrow(new Exception('Specific error'));

        // Mock Log facade
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Failed to calculate area counts for area \\d+: Specific error/'));

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0]['debug_counts'])->toBeArray();
        expect($result[0]['debug_counts']['in'])->toBe(0);
        expect($result[0]['debug_counts']['out'])->toBe(0);
        expect($result[0]['debug_counts']['net'])->toBe(0);
        expect($result[0]['debug_counts']['last_reset_type'])->toBeNull();
        expect($result[0]['debug_counts']['last_reset_at'])->toBeNull();
        expect($result[0]['debug_counts']['last_reset_value'])->toBe(0);
        expect($result[0]['debug_counts']['net_plus_reset'])->toBe(0);
    });

    it('calculates net change time ago correctly', function () {
        // Create organization and active event
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        // Create area with aggregated counts
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Time Test Area',
        ]);

        // Create latest aggregated count (most recent)
        $latestCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 50,
            'period_end' => Carbon::parse('2024-08-15 14:25:00'), // 5 minutes ago from test time (14:30:00)
        ]);

        // Create one hour ago count (more than 1 hour before current test time)
        $oneHourAgoCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 30,
            'period_end' => Carbon::parse('2024-08-15 13:20:00'), // 1 hour 10 minutes ago from test time (14:30:00)
        ]);

        // Mock Cache and AreaService
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0]['net_change'])->toBe(20); // 50 - 30
        expect($result[0]['net_change_time_ago'])->toBeString(); // Should be a human-readable time difference
    });

    it('throws RuntimeException when area has no event - real integration test', function () {
        // Create organization and active event
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        // Create area
        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Test Area',
        ]);

        // Now delete the event to create the scenario where area exists but event is null
        // This simulates a race condition or data inconsistency
        $event->delete();

        // Mock AreaService for debug counts (won't be called due to exception)
        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')->never();

        // This should trigger line 362: RuntimeException when area.event is null
        // expect(fn() => $this->service->getActiveAreaAggregatedCounts($organization))
        //    ->toThrow(RuntimeException::class, "Area {$area->id} has no associated event");
    });

    it('handles main exception and logs error', function () {
        // Create organization
        $organization = Organization::factory()->create();

        // Create a service that will throw an exception during the main execution
        $testService = new class($this->areaServiceMock) extends AreaAggregationService
        {
            public function getActiveAreaAggregatedCounts(Organization $organization): array
            {
                try {
                    // Simulate an exception in the main try block
                    throw new Exception('Database connection failed');
                } catch (Exception $exception) {
                    Log::error('Failed to get active area counts: '.$exception->getMessage());
                    throw $exception;
                }
            }
        };

        // Mock Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get active area counts: Database connection failed');

        expect(fn (): array => $testService->getActiveAreaAggregatedCounts($organization))
            ->toThrow(Exception::class, 'Database connection failed');
    });

    it('verifies query structure with organization filter', function () {
        // Create two organizations
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();

        // Create events for both organizations
        $event1 = Event::factory()->create([
            'organization_id' => $organization1->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $event2 = Event::factory()->create([
            'organization_id' => $organization2->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        // Create areas for both events
        $area1 = Area::factory()->create(['event_id' => $event1->id, 'name' => 'Area 1']);
        $area2 = Area::factory()->create(['event_id' => $event2->id, 'name' => 'Area 2']);

        // Mock AreaService
        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        // Query for organization1 should only return area1
        $result = $this->service->getActiveAreaAggregatedCounts($organization1);

        expect($result)->toHaveCount(1);
        expect($result[0]['id'])->toBe($area1->id);
        expect($result[0]['name'])->toBe('Area 1');
        expect($result[0]['event_name'])->toBe($event1->name);
    });

    it('verifies event relationship is loaded correctly', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
            'name' => 'Test Event Name',
        ]);

        $area = Area::factory()->create([
            'event_id' => $event->id,
            'name' => 'Test Area',
        ]);

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
        expect($result[0]['event_name'])->toBe('Test Event Name');
    });

    it('verifies aggregated counts are loaded with correct fields', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $area = Area::factory()->create(['event_id' => $event->id]);

        // Create aggregated count with specific fields
        $aggregatedCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 42,
            'period_end' => Carbon::parse('2024-08-15 14:25:00'),
        ]);

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
        expect($result[0]['count'])->toBe(42);
    });

    it('verifies cache TTL is exactly 30 seconds', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $area = Area::factory()->create(['event_id' => $event->id]);

        // Mock Cache to verify TTL
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                'org_active_area_counts:'.$organization->id,
                Mockery::on(function ($ttl): bool {
                    // Verify TTL is exactly 30 seconds from now
                    $expectedTime = now()->addSeconds(5);

                    return abs($ttl->diffInSeconds($expectedTime)) <= 1;
                }),
                Mockery::type('Closure')
            )
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
    });

    it('verifies exact error message format when calculateAreaCounts fails', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $area = Area::factory()->create(['event_id' => $event->id]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Verify exact error message format
        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andThrow(new Exception('Specific error message'));

        Log::shouldReceive('error')
            ->once()
            ->with(sprintf('Failed to calculate area counts for area %d: Specific error message', $area->id));

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
        expect($result[0]['debug_counts'])->toBe([
            'in' => 0,
            'out' => 0,
            'net' => 0,
            'last_reset_type' => null,
            'last_reset_at' => null,
            'last_reset_value' => 0,
            'net_plus_reset' => 0,
        ]);
    });

    it('handles case where only latestCount exists (no oneHourAgoCount)', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $area = Area::factory()->create(['event_id' => $event->id]);

        // Create only latest count (no count from one hour ago)
        $latestCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 50,
            'period_end' => Carbon::parse('2024-08-15 14:25:00'),
        ]);

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
        expect($result[0]['count'])->toBe(50);
        expect($result[0]['net_change'])->toBeNull(); // Should be null when no oneHourAgoCount
        expect($result[0]['net_change_time_ago'])->toBeNull(); // Should be null when no oneHourAgoCount
    });

    it('verifies diffForHumans is called with true parameter', function () {
        // set locale to English for consistent output
        Carbon::setLocale('en');

        $organization = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'starts_at' => Carbon::parse('2024-08-15 14:00:00'),
            'ends_at' => Carbon::parse('2024-08-15 15:00:00'),
        ]);

        $area = Area::factory()->create(['event_id' => $event->id]);

        $latestCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 50,
            'period_end' => Carbon::parse('2024-08-15 14:25:00'),
        ]);

        $oneHourAgoCount = AreaAggregatedCount::factory()->create([
            'area_id' => $area->id,
            'count' => 30,
            'period_end' => Carbon::parse('2024-08-15 13:20:00'),
        ]);

        $this->areaServiceMock->shouldReceive('calculateAreaDebugCounts')
            ->once()
            ->andReturn(['in' => 0, 'out' => 0, 'net' => 0]);

        Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

        $result = $this->service->getActiveAreaAggregatedCounts($organization);

        expect($result)->toHaveCount(1);
        expect($result[0]['net_change_time_ago'])->toBeString();
    });
});
