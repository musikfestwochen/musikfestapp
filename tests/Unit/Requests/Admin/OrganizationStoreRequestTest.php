<?php

use App\Http\Requests\Admin\OrganizationStoreRequest;
use App\Models\User;

covers(OrganizationStoreRequest::class);

beforeEach(function () {
    $this->request = new OrganizationStoreRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules([
        'name' => ['required', 'string', 'max:255', 'unique:organizations'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ], $this->request->rules());
});

it('authorizes when user can store organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('organizations.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
