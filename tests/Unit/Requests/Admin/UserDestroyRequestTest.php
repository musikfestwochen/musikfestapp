<?php

use App\Http\Requests\Admin\UserDestroyRequest;

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserDestroyRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserDestroyRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns empty array', function () {
    $request = new UserDestroyRequest;
    expect($request->rules())->toBe([]);
});
