<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Peoplecount\UpdateAreaAggregations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyPeoplecountAggregationRequest;
use App\Http\Requests\Admin\UpdatePeoplecountAggregationRequest;
use Illuminate\Http\RedirectResponse;

class PeoplecountAggregationController extends Controller
{
    public function update(UpdatePeoplecountAggregationRequest $request, UpdateAreaAggregations $updateAreaAggregations): RedirectResponse
    {
        if (! $updateAreaAggregations->handle()) {
            return back()->with('status', 'Peoplecount aggregation is already running.');
        }

        return back()->with('status', 'Peoplecount aggregations updated.');
    }

    public function destroy(DestroyPeoplecountAggregationRequest $request, UpdateAreaAggregations $updateAreaAggregations): RedirectResponse
    {
        if (! $updateAreaAggregations->handle(truncate: true)) {
            return back()->with('status', 'Peoplecount aggregation is already running.');
        }

        return back()->with('status', 'Peoplecount aggregations rebuilt.');
    }
}
