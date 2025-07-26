<?php

use App\Http\Requests\Peoplecount\AssignmentShowRequest;
use App\Models\User;

covers(AssignmentShowRequest::class);

beforeEach(function () {
    $this->request = new AssignmentShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can show assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
