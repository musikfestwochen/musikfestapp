<?php

use App\Http\Requests\Peoplecount\AssignmentIndexRequest;
use App\Models\User;

covers(AssignmentIndexRequest::class);

beforeEach(function () {
    $this->request = new AssignmentIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
