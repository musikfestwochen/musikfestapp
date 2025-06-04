<?php

use App\Http\Requests\Admin\UserEditRequest;

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new UserEditRequest();
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new UserEditRequest();
    expect($request->authorize())->toBeFalse();
});

test('rules returns empty array', function () {
    $request = new UserEditRequest();
    expect($request->rules())->toBe([]);
});
