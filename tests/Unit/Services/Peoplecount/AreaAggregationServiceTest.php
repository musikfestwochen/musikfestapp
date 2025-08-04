<?php

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Services\Peoplecount\AreaAggregationService;
use App\Services\Peoplecount\AreaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

covers(AreaAggregationService::class);

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
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
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
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if no exception thrown
    });

    it('verifies deleteInvalidAggregationRows is called by testing its side effects', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
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
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);

        // Mock for deleteRowsWithInvalidChecksum - this verifies deleteInvalidAggregationRows is called
        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('delete')->once(); // This expectation will fail if deleteInvalidAggregationRows is not called
        $relationshipMock->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if delete() was called, proving deleteInvalidAggregationRows was executed
    });

    it('verifies calculateAggregatedCountsForWindows is called by testing its side effects', function () {
        $area = Mockery::mock(Area::class);
        $area->shouldReceive('load')->once();

        // Mock assignments collection with data
        $assignment = Mockery::mock(Assignment::class);
        $assignments = new EloquentCollection([$assignment]);
        $area->shouldReceive('getAttribute')->with('assignments')->andReturn($assignments);

        // Mock aggregatedCounts property access
        $aggregatedCounts = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($aggregatedCounts);

        // Mock area service calls
        $this->areaServiceMock->shouldReceive('calculateChecksum')->once()->andReturn('abc123');
        $this->areaServiceMock->shouldReceive('getAreaResets')->once()->andReturn(collect());

        // This expectation will fail if calculateAggregatedCountsForWindows is not called
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')
            ->once()
            ->with(
                $area,
                Mockery::type(Carbon::class),
                Mockery::type(Carbon::class),
                0,
                'abc123'
            )
            ->andReturn(10);

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock aggregatedCounts relationship for filtering and calculations
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $this->service->updateAggregatedCounts($area);

        expect(true)->toBeTrue(); // Test passes if calculateAndStoreAggregatedCount was called, proving calculateAggregatedCountsForWindows was executed
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

describe('generateWindows method', function () {
    it('generates correct number of windows', function () {
        $config = [
            'windowSize' => 10,
            'startTime' => Carbon::parse('2024-08-15 10:00:00'),
            'endTime' => Carbon::parse('2024-08-15 11:00:00'),
        ];
        $resetTimes = collect();

        $result = ($this->callProtectedMethod)('generateWindows', $config, $resetTimes);

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
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect();

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventEndTime, $windowSize, $resetTimes);

        expect($result->format('H:i'))->toBe('10:10');
    });

    it('returns reset time when reset occurs within window', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:05:00')],
        ]);

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventEndTime, $windowSize, $resetTimes);

        expect($result->format('H:i'))->toBe('10:05');
    });

    it('returns natural window end when reset is exactly at start time', function () {
        $startTime = Carbon::parse('2024-08-15 10:00:00');
        $eventEndTime = Carbon::parse('2024-08-15 18:00:00');
        $windowSize = 10;
        $resetTimes = collect([
            ['at' => Carbon::parse('2024-08-15 10:00:00')], // Reset exactly at start time
        ]);

        $result = ($this->callProtectedMethod)('calculateWindowEnd', $startTime, $eventEndTime, $windowSize, $resetTimes);

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

describe('filterFutureWindows method', function () {
    it('filters out future windows', function () {
        $pastWindow = ['start' => Carbon::parse('2024-08-15 12:00:00')];
        $futureWindow = ['start' => Carbon::parse('2024-08-15 16:00:00')];
        $windows = collect([$pastWindow, $futureWindow]);

        $result = ($this->callProtectedMethod)('filterFutureWindows', $windows);

        expect($result)->toHaveCount(1);
        expect($result->first()['start']->format('H:i'))->toBe('12:00');
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
        $builderMock1->shouldReceive('delete')->once();

        $relationshipMock1 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock1->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock1);

        // Mock for deleteRowsWithInvalidWindowSize - second call (median matches config, so no delete)
        // Even though no delete happens, the method still accesses aggregatedCounts()
        $relationshipMock2 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        // No get() expectation needed since median matches config and delete won't be called

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock1, $relationshipMock2);

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
        $builderMock1->shouldReceive('delete')->once();

        $relationshipMock1 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock1->shouldReceive('where')->with('checksum', '!=', hex2bin('abc123'))->andReturn($builderMock1);

        // Mock for deleteRowsWithInvalidWindowSize - second call (median differs from config, so get()->each->delete())
        // Create a real collection with the mock model so each->delete() works properly
        $realCollection = new EloquentCollection([$count]);

        $relationshipMock2 = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock2->shouldReceive('get')->once()->andReturn($realCollection);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock1, $relationshipMock2);

        // Mock for deleteRowsWithInvalidWindowSize - median differs from config (15 minutes vs 10)
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $count->shouldReceive('delete')->once();

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
        $builderMock->shouldReceive('delete')->once();
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidChecksum', $area, 'abc123');

        expect($result)->toBeNull();
    });
});

describe('deleteRowsWithInvalidWindowSize method', function () {
    it('returns early when aggregated counts are empty', function () {
        $area = Mockery::mock(Area::class);
        $emptyCollection = new EloquentCollection;
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($emptyCollection);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidWindowSize', $area);

        expect($result)->toBeNull();
    });

    it('deletes all counts when median window size differs from config', function () {
        $area = Mockery::mock(Area::class);

        // Create counts with different window size (15 minutes instead of 10)
        $count = Mockery::mock(AreaAggregatedCount::class);
        $count->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $count->shouldReceive('getAttribute')->with('period_end')->andReturn(Carbon::parse('2024-08-15 10:15:00'));
        $count->shouldReceive('delete')->once();
        $counts = new EloquentCollection([$count]);
        $area->shouldReceive('getAttribute')->with('aggregatedCounts')->andReturn($counts);

        // Mock relationship for get()->each->delete() pattern
        // Create a real collection with the mock model so each->delete() works properly
        $realCollection = new EloquentCollection([$count]);

        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('get')->once()->andReturn($realCollection);
        $area->shouldReceive('aggregatedCounts')->once()->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('deleteRowsWithInvalidWindowSize', $area);

        expect($result)->toBeNull();
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

        expect($result)->toBeNull();
    });
});

describe('getFilteredAggregationWindows method', function () {
    it('filters aggregation windows correctly', function () {
        $area = Mockery::mock(Area::class);
        $resetTimes = collect();

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        // Mock aggregatedCounts relationship for filtering
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('getFilteredAggregationWindows', $area, $resetTimes);

        expect($result)->toBeInstanceOf(Collection::class);
    });
});

describe('splitIntoAggregationWindows method', function () {
    it('splits reset times into aggregation windows', function () {
        $area = Mockery::mock(Area::class);
        $resetTimes = collect();

        // Mock event for window configuration
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getAttribute')->with('starts_at')->andReturn(Carbon::parse('2024-08-15 10:00:00'));
        $event->shouldReceive('getAttribute')->with('ends_at')->andReturn(Carbon::parse('2024-08-15 10:10:00'));
        $area->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $result = ($this->callProtectedMethod)('splitIntoAggregationWindows', $area, $resetTimes);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(1); // 10 minute window
    });
});

describe('filterAlreadyAggregatedWindows method', function () {
    it('returns all windows when no last aggregated count exists', function () {
        $area = Mockery::mock(Area::class);
        $windows = collect([
            ['start' => Carbon::parse('2024-08-15 10:00:00')],
            ['start' => Carbon::parse('2024-08-15 10:10:00')],
        ]);

        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('filterAlreadyAggregatedWindows', $area, $windows);

        expect($result)->toHaveCount(2);
    });

    it('filters windows based on last aggregated count', function () {
        $area = Mockery::mock(Area::class);
        $windows = collect([
            ['start' => Carbon::parse('2024-08-15 09:50:00')],
            ['start' => Carbon::parse('2024-08-15 10:00:00')],
            ['start' => Carbon::parse('2024-08-15 10:10:00')],
        ]);

        $lastCount = Mockery::mock(AreaAggregatedCount::class);
        $lastCount->shouldReceive('getAttribute')->with('period_start')->andReturn(Carbon::parse('2024-08-15 10:00:00'));

        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn($lastCount);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('filterAlreadyAggregatedWindows', $area, $windows);

        expect($result)->toHaveCount(2); // Should filter out the first window
    });
});

describe('calculateAggregatedCountsForWindows method', function () {
    it('calculates and stores aggregated counts for windows', function () {
        $area = Mockery::mock(Area::class);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => null,
            ],
        ]);
        $checksum = 'abc123';

        // Mock getInitialCountForAggregation
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        // Mock area service call
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')
            ->once()
            ->with($area, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 0, $checksum)
            ->andReturn(15);

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum);

        expect($result)->toBeNull();
    });

    it('uses reset_value when provided instead of lastCount', function () {
        $area = Mockery::mock(Area::class);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => 50, // Specific reset value provided
            ],
        ]);
        $checksum = 'abc123';

        // Mock getInitialCountForAggregation to return a different value
        $relationshipMock = Mockery::mock(HasMany::class);
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        // Mock area service call - should use reset_value (50) not lastCount (0)
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')
            ->once()
            ->with($area, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 50, $checksum)
            ->andReturn(15);

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum);

        expect($result)->toBeNull();
    });

    it('uses lastCount when reset_value is null', function () {
        $area = Mockery::mock(Area::class);
        $windows = collect([
            [
                'start' => Carbon::parse('2024-08-15 10:00:00'),
                'end' => Carbon::parse('2024-08-15 10:10:00'),
                'reset_value' => null, // No reset value
            ],
        ]);
        $checksum = 'abc123';

        // Mock getInitialCountForAggregation to return a specific value
        $secondLastCount = Mockery::mock(AreaAggregatedCount::class);
        $secondLastCount->shouldReceive('getAttribute')->with('count')->andReturn(25);

        $relationshipMock = Mockery::mock(HasMany::class);
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn($secondLastCount);
        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        // Mock area service call - should use lastCount (25) since reset_value is null
        $this->areaServiceMock->shouldReceive('calculateAndStoreAggregatedCount')
            ->once()
            ->with($area, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 25, $checksum)
            ->andReturn(15);

        $result = ($this->callProtectedMethod)('calculateAggregatedCountsForWindows', $area, $windows, $checksum);

        expect($result)->toBeNull();
    });
});

describe('getInitialCountForAggregation method', function () {
    it('returns 0 when no second last aggregated count exists', function () {
        $area = Mockery::mock(Area::class);

        // Create a proper HasMany relationship mock that can handle method chaining
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn(null);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('getInitialCountForAggregation', $area);

        expect($result)->toBe(0);
    });

    it('returns count from second last aggregated count', function () {
        $area = Mockery::mock(Area::class);
        $secondLastCount = Mockery::mock(AreaAggregatedCount::class);
        $secondLastCount->shouldReceive('getAttribute')->with('count')->andReturn(25);

        // Create a proper HasMany relationship mock that can handle method chaining
        $relationshipMock = Mockery::mock(HasMany::class)->shouldIgnoreMissing();
        $relationshipMock->shouldReceive('latest')->with('period_end')->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('skip')->with(1)->andReturn($relationshipMock);
        $relationshipMock->shouldReceive('first')->andReturn($secondLastCount);

        $area->shouldReceive('aggregatedCounts')->andReturn($relationshipMock);

        $result = ($this->callProtectedMethod)('getInitialCountForAggregation', $area);

        expect($result)->toBe(25);
    });
});
