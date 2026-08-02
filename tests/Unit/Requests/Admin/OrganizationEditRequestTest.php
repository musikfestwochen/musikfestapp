<?php

use App\Http\Requests\Admin\OrganizationEditRequest;
use App\Models\User;

covers(OrganizationEditRequest::class);

beforeEach(function () {
    $this->request = new OrganizationEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can edit organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
