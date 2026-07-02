<?php

declare(strict_types=1);

namespace App\Actions\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Services\Peoplecount\AlertService;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Support\Facades\Cache;

class UpdateAreaAggregations
{
    public const string LOCK_KEY = 'peoplecount:area-aggregations:update';

    private const int LOCK_SECONDS = 240;

    public function __construct(
        private readonly AreaAggregationService $areaAggregationService,
        private readonly AlertService $alertService,
    ) {}

    public function handle(bool $truncate = false): bool
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            if ($truncate) {
                AreaAggregatedCount::query()->truncate();
            }

            Area::query()->each(function (Area $area): void {
                $this->areaAggregationService->updateAggregatedCounts($area);
                $this->alertService->processAlertsForArea($area);
            });

            return true;
        } finally {
            $lock->release();
        }
    }
}
