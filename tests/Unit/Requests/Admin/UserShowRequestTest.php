<?php

use App\Http\Requests\Admin\UserShowRequest;

covers(UserShowRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserShowRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserShowRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns empty array', function () {
    $request = new UserShowRequest;
    expect($request->rules())->toBe([]);
});
