<?php

use App\Http\Requests\Orgmgmt\UserStoreRequest;
use App\Models\User;

covers(UserStoreRequest::class);

beforeEach(function () {
    $this->request = new UserStoreRequest;
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules['name'])->toBe(['required', 'string', 'max:255'])
        ->and($rules['email'])->toBe(['required', 'string', 'email', 'max:255'])
        ->and($rules['phone'][0])->toBe('nullable')
        ->and($rules['phone'][1])->toBe('string')
        ->and($rules['phone'][2])->toBe('max:20')
        ->and($rules['phone'][3])->toBeInstanceOf(Closure::class)
        ->and($rules['roles'][0])->toBe('sometimes')
        ->and($rules['roles'][1])->toBe('array')
        ->and($rules['roles'][2])->toBe('list')
        ->and($rules['roles'][3])->toBe('min:1')
        ->and($rules['roles'][4])->toBeInstanceOf(Closure::class)
        ->and($rules['roles.*'][0])->toBe('string')
        ->and($rules['roles.*'][1])->toBe('distinct');
});

it('skips phone uniqueness validation when the phone is blank', function () {
    $rules = $this->request->rules();
    $messages = [];

    $rules['phone'][3]('phone', '', function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([]);
});

it('authorizes when user can store users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('orgmgmt.users.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
