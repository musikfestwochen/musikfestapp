<?php

use App\Http\Requests\Admin\OrganizationDestroyRequest;
use App\Models\User;

covers(OrganizationDestroyRequest::class);

beforeEach(function () {
    $this->request = new OrganizationDestroyRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can destroy organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
