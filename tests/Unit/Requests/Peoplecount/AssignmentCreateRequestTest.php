<?php

use App\Http\Requests\Peoplecount\AssignmentCreateRequest;
use App\Models\User;

covers(AssignmentCreateRequest::class);

beforeEach(function () {
    $this->request = new AssignmentCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
