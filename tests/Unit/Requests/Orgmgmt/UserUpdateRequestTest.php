<?php

use App\Http\Requests\Orgmgmt\UserUpdateRequest;
use App\Models\User;

covers(UserUpdateRequest::class);

beforeEach(function () {
    $this->request = new UserUpdateRequest;
});

it('authorizes when user can update users', function () {
    $user = Mockery::mock(User::class);
    // Updated to match the actual permission string used in the request
    $user->shouldReceive('can')->with('orgmgmt.users.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('has correct rules with user ID', function () {
    // Create a mock user
    $user = new User;
    $user->id = 123;

    // Create the request and set the user
    $request = new UserUpdateRequest;
    $request->setRouteResolver(function () use ($user): object {
        return new class($user)
        {
            protected $user;

            public function __construct($user)
            {
                $this->user = $user;
            }

            public function parameter($name)
            {
                return $name === 'user' ? $this->user : null;
            }
        };
    });

    $rules = $request->rules();

    expect($rules['name'])->toBe(['required', 'string', 'max:255'])
        ->and($rules['email'])->toBe(['required', 'string', 'email', 'max:255', 'unique:users,email,123'])
        ->and($rules['phone'])->toBe(['nullable', 'string', 'max:20', 'unique:users,phone,123'])
        ->and($rules['roles'][0])->toBe('sometimes')
        ->and($rules['roles'][1])->toBe('array')
        ->and($rules['roles'][2])->toBe('list')
        ->and($rules['roles'][3])->toBe('min:1')
        ->and($rules['roles'][4])->toBeInstanceOf(Closure::class)
        ->and($rules['roles.*'][0])->toBe('string')
        ->and($rules['roles.*'][1])->toBe('distinct');
});

it('has correct rules with null user', function () {
    // Create the request and set the user to null
    $request = new UserUpdateRequest;
    $request->setRouteResolver(function (): object {
        return new class
        {
            public function parameter($name): null
            {
                return null;
            }
        };
    });

    $rules = $request->rules();

    expect($rules['name'])->toBe(['required', 'string', 'max:255'])
        ->and($rules['email'])->toBe(['required', 'string', 'email', 'max:255', 'unique:users,email,'])
        ->and($rules['phone'])->toBe(['nullable', 'string', 'max:20', 'unique:users,phone,'])
        ->and($rules['roles'][4])->toBeInstanceOf(Closure::class);
});
