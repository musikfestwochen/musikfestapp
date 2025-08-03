<?php

namespace App\Jobs;

use App\Models\Peoplecount\Area;
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
            app(\App\Services\Peoplecount\AreaAggregationService::class)->updateAggregatedCounts($area);
        }
    }
}
