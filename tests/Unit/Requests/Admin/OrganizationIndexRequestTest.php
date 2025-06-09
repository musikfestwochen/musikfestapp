<?php

use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Models\User;

covers(OrganizationIndexRequest::class);

beforeEach(function () {
    $this->request = new OrganizationIndexRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules([
        'sort' => 'in:name,email,website',
        'order' => 'in:asc,desc',
    ], $this->request->rules());
});

it('authorizes when user can index organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
