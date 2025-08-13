<?php

use App\Http\Requests\Settings\AppearanceUpdateRequest;

covers(AppearanceUpdateRequest::class);

beforeEach(function () {
    $this->request = new AppearanceUpdateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'eastereggs_activated' => ['required', 'boolean'],
    ]);
});

it('authorizes when user is authenticated', function () {
    Auth::shouldReceive('check')->andReturn(true);

    expect($this->request->authorize())->toBeTrue();
});
