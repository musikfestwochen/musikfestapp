<?php

use App\Http\Requests\Peoplecount\AssignmentEditRequest;
use App\Models\User;

covers(AssignmentEditRequest::class);

beforeEach(function () {
    $this->request = new AssignmentEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can edit assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
