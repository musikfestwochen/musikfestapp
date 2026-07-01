<?php

use App\Http\Requests\Peoplecount\AreaUpdateRequest;
use App\Models\User;

covers(AreaUpdateRequest::class);

beforeEach(function () {
    $this->request = new AreaUpdateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'name' => ['required', 'string', 'max:255'],
        'event_id' => ['required', 'integer', 'exists:peoplecount_events,id'],
    ]);
});

it('returns typed payload values', function () {
    $data = ['name' => 'Main Gate', 'event_id' => '42'];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'name' => ['required', 'string'],
        'event_id' => ['required', 'integer'],
    ]));

    expect($this->request->payload())->toBe([
        'name' => 'Main Gate',
        'event_id' => 42,
    ]);
});

it('authorizes when user can update areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
