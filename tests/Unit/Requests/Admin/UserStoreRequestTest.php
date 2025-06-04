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

    expect($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('email')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['name'])->toContain('max:255')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('string')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255')
        ->and($rules['email'])->toContain('unique:users');

    // Check name validation rules

    // Check email validation rules
});
