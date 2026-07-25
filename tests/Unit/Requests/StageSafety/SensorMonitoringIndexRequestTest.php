<?php

use App\Http\Requests\StageSafety\SensorMonitoringIndexRequest;
use App\Models\User;

covers(SensorMonitoringIndexRequest::class);

it('uses the sensor show permission', function (bool $allowed) {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.show')->andReturn($allowed);
    $request = new SensorMonitoringIndexRequest;
    $request->setUserResolver(fn (): User => $user);

    expect($request->authorize())->toBe($allowed);
})->with([true, false]);
