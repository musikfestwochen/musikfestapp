<?php

use App\Models\Peoplecount\Sensor;
use App\Models\User;
use App\Services\Peoplecount\IntervalCountService;
use App\Services\Peoplecount\SensorService;
use Illuminate\Contracts\Auth\Factory;

it('returns 429 after 60 requests from the same IP address', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 60) as $request) {
        $this->postJson(route('peoplecount.interval-count.store'), [])
            ->assertForbidden();
    }

    $this->postJson(route('peoplecount.interval-count.store'), [])
        ->assertTooManyRequests();
});

it('gives each sensor token an independent rate limit', function () {
    $firstSensor = Sensor::factory()->create();
    $secondSensor = Sensor::factory()->create();
    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')->twice()->andReturn(0);
    $this->app->instance(IntervalCountService::class, $mock);

    $firstToken = $firstSensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;
    $secondToken = $secondSensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), [], [
        'Authorization' => 'Bearer '.$firstToken,
    ])->assertOk()->assertHeader('X-RateLimit-Remaining', '59');

    $this->app->make(Factory::class)->forgetGuards();

    $this->postJson(route('peoplecount.interval-count.store'), [], [
        'Authorization' => 'Bearer '.$secondToken,
    ])->assertOk()->assertHeader('X-RateLimit-Remaining', '59');
});
