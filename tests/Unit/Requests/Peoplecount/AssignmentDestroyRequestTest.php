<?php

use App\Http\Requests\Peoplecount\AssignmentDestroyRequest;
use App\Models\User;

covers(AssignmentDestroyRequest::class);

beforeEach(function () {
    $this->request = new AssignmentDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can destroy assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
