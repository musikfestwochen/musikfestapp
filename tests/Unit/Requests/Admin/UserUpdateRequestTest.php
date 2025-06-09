<?php

use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(UserUpdateRequest::class);

beforeEach(function () {
    $this->request = new UserUpdateRequest;
});

it('authorizes when user can update users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('users.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
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

    $this->assertExactValidationRules([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,123'],
    ], $request->rules());
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

    $this->assertExactValidationRules([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'],
    ], $request->rules());
});
