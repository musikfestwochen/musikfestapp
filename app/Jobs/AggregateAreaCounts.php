<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Peoplecount\UpdateAreaAggregations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateAreaCounts implements ShouldQueue
{
    use Queueable;

    public function handle(UpdateAreaAggregations $updateAreaAggregations): void
    {
        $updateAreaAggregations->handle();
    }
}
