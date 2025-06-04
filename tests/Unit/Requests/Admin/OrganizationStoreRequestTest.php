<?php

use App\Http\Requests\Admin\OrganizationStoreRequest;

covers(OrganizationStoreRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationStoreRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationStoreRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationStoreRequest;
    $rules = $request->rules();

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255', 'unique:organizations'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
});
