<?php

use App\Http\Requests\Admin\UserStoreRequest;

covers(UserStoreRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserStoreRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserStoreRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new UserStoreRequest;
    $rules = $request->rules();

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
});
