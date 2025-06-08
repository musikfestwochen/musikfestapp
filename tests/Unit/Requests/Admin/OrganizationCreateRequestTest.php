<?php

use App\Http\Requests\Admin\OrganizationCreateRequest;
use App\Models\User;

covers(OrganizationCreateRequest::class);

beforeEach(function () {
    $this->request = new OrganizationCreateRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can create organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('organizations.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
