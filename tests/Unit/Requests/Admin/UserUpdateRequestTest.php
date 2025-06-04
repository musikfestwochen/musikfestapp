<?php

use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;

covers(UserUpdateRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserUpdateRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserUpdateRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules with user ID', function () {
    // Create a mock User model with an ID
    $user = new User;
    $user->id = 123;

    // Create a partial mock of the UserUpdateRequest class
    $request = Mockery::mock(UserUpdateRequest::class)->makePartial();
    $request->shouldReceive('route')
        ->with('user')
        ->andReturn($user);

    // Get the rules
    $rules = $request->rules();

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,123'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
});

test('rules returns expected validation rules with null user', function () {
    // Create a partial mock of the UserUpdateRequest class
    $request = Mockery::mock(UserUpdateRequest::class)->makePartial();
    $request->shouldReceive('route')
        ->with('user')
        ->andReturn(null);

    // Get the rules
    $rules = $request->rules();

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
});
