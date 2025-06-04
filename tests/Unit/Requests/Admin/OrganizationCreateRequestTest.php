<?php

use App\Http\Requests\Admin\OrganizationCreateRequest;

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationCreateRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationCreateRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationCreateRequest;
    $rules = $request->rules();

    // The create request doesn't have any specific validation rules
    expect($rules)->toBeArray()->toBeEmpty();
});
