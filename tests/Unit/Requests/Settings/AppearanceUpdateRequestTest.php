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

it('returns typed easter eggs flag', function () {
    expect($this->request->eastereggsActivated())->toBeFalse();

    $this->request->merge(['eastereggs_activated' => '1']);

    expect($this->request->eastereggsActivated())->toBeTrue();
});

it('authorizes when user is authenticated', function () {
    Auth::shouldReceive('check')->andReturn(true);

    expect($this->request->authorize())->toBeTrue();
});
