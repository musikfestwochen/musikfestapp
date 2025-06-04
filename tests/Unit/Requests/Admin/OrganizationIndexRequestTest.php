<?php

use App\Http\Requests\Admin\OrganizationIndexRequest;

covers(OrganizationIndexRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationIndexRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationIndexRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules', function () {
    $request = new OrganizationIndexRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('sort')
        ->and($rules)->toHaveKey('order')
        ->and($rules['sort'])->toBe('in:name,email,website')
        ->and($rules['order'])->toBe('in:asc,desc');
});
