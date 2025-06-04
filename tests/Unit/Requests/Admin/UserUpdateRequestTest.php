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

test('rules returns expected validation rules', function () {
    // Create a mock User model with an ID
    $user = new User;
    $user->id = 123;

    // Create a partial mock of the UserUpdateRequest class
    $request = \Mockery::mock(UserUpdateRequest::class)->makePartial();
    $request->shouldReceive('route')
        ->with('user')
        ->andReturn($user);

    // Get the rules
    $rules = $request->rules();

    expect($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('email')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['name'])->toContain('max:255')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('string')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255')
        ->and($rules['email'][4])->toContain('unique:users,email,123');

    // Check name validation rules

    // Check email validation rules

    // Check the unique rule contains the user ID

    // If the test fails, output the actual value for debugging
    if (! str_contains($rules['email'][4], 'unique:users,email,123')) {
        test()->fail('Expected unique rule to contain user ID. Actual value: '.$rules['email'][4]);
    }
});
