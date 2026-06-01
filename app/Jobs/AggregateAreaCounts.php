<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Peoplecount\Area;
use App\Services\Peoplecount\AlertService;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateAreaCounts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $areas = Area::query()->get();

        foreach ($areas as $area) {
            // Update aggregated counts for each area
            resolve(AreaAggregationService::class)->updateAggregatedCounts($area);

            // After aggregation, process alerts for the given area
            resolve(AlertService::class)->processAlertsForArea($area);
        }
    }
}
