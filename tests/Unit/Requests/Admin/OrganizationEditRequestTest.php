<?php

use App\Http\Requests\Admin\OrganizationEditRequest;

covers(OrganizationEditRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationEditRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationEditRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationEditRequest;
    $rules = $request->rules();

    // The edit request doesn't have any specific validation rules
    expect($rules)->toBeArray()->toBeEmpty();
});
