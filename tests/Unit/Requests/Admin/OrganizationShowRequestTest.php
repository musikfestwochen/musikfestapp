<?php

use App\Http\Requests\Admin\OrganizationShowRequest;

covers(OrganizationShowRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationShowRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationShowRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationShowRequest;
    $rules = $request->rules();

    // The show request doesn't have any specific validation rules
    expect($rules)->toBeArray()->toBeEmpty();
});
