<?php

use App\Http\Requests\Admin\OrganizationStoreRequest;
use App\Models\User;

covers(OrganizationStoreRequest::class);

beforeEach(function () {
    $this->request = new OrganizationStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'name' => ['required', 'string', 'max:255', 'unique:organizations'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ]);
});

it('authorizes when user can store organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
