<?php

use App\Http\Requests\Admin\OrganizationEditRequest;
use App\Models\User;

covers(OrganizationEditRequest::class);

beforeEach(function () {
    $this->request = new OrganizationEditRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can edit organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('organizations.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
