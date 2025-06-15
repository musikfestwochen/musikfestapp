<?php

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

covers(LoginRequest::class);

beforeEach(function () {
    $this->request = new LoginRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);
});

it('authorizes all users', function () {
    expect($this->request->authorize())->toBeTrue();
});

it('authenticates with valid credentials', function () {
    $this->request->merge([
        'email' => 'test@example.com',
        'password' => 'password123',
        'remember' => false,
    ]);

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->with($this->request->throttleKey(), 5)
        ->andReturn(false);

    Auth::shouldReceive('attempt')
        ->once()
        ->with(['email' => 'test@example.com', 'password' => 'password123'], false)
        ->andReturn(true);

    RateLimiter::shouldReceive('clear')
        ->once()
        ->with($this->request->throttleKey());

    $this->request->authenticate();
});

it('throws validation exception with invalid credentials', function () {
    $this->request->merge([
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
        'remember' => false,
    ]);

    Auth::shouldReceive('attempt')
        ->once()
        ->with(['email' => 'test@example.com', 'password' => 'wrongpassword'], false)
        ->andReturn(false);

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->with($this->request->throttleKey(), 5)
        ->andReturn(false);

    RateLimiter::shouldReceive('hit')
        ->once()
        ->with($this->request->throttleKey());

    expect(fn () => $this->request->authenticate())
        ->toThrow(ValidationException::class);
});

it('handles rate limiting correctly', function () {
    $this->request->merge([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->with($this->request->throttleKey(), 5)
        ->andReturn(true);

    RateLimiter::shouldReceive('availableIn')
        ->once()
        ->with($this->request->throttleKey())
        ->andReturn(60);

    Event::shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(Lockout::class));

    expect(fn () => $this->request->ensureIsNotRateLimited())
        ->toThrow(ValidationException::class);
});

it('generates correct throttle key', function () {
    $this->request->merge(['email' => 'Test@Example.com']);
    $this->request->setMethod('POST');
    $this->request->server->set('REMOTE_ADDR', '192.168.1.1');

    $expectedKey = 'test@example.com|192.168.1.1';

    expect($this->request->throttleKey())->toBe($expectedKey);
});

it('does not rate limit when under threshold', function () {
    RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->with($this->request->throttleKey(), 5)
        ->andReturn(false);

    // Should not throw any exception
    $this->request->ensureIsNotRateLimited();

    expect(true)->toBeTrue(); // Test passes if no exception is thrown
});
