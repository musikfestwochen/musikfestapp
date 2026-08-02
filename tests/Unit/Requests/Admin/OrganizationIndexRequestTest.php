<?php

use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Models\User;

covers(OrganizationIndexRequest::class);

beforeEach(function () {
    $this->request = new OrganizationIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can index organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
