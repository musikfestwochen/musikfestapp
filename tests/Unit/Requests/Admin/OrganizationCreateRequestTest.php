<?php

use App\Http\Requests\Admin\OrganizationCreateRequest;
use App\Models\User;

covers(OrganizationCreateRequest::class);

beforeEach(function () {
    $this->request = new OrganizationCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
