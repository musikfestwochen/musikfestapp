<?php

use App\Http\Requests\Admin\OrganizationDestroyRequest;

covers(OrganizationDestroyRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationDestroyRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationDestroyRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationDestroyRequest;
    $rules = $request->rules();

    // The destroy request doesn't have any specific validation rules
    expect($rules)->toBeArray()->toBeEmpty();
});
