<?php

use App\Http\Requests\Admin\OrganizationShowRequest;
use App\Models\User;

covers(OrganizationShowRequest::class);

beforeEach(function () {
    $this->request = new OrganizationShowRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can show organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
