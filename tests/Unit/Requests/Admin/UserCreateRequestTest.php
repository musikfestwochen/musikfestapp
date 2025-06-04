<?php

use App\Http\Requests\Admin\UserCreateRequest;

covers(UserCreateRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserCreateRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserCreateRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns empty array', function () {
    $request = new UserCreateRequest;
    expect($request->rules())->toBe([]);
});
