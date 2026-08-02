<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

covers(LoginRequest::class);

function createLoginRequest(string $email, string $password, bool $remember = false, string $ip = '127.0.0.1'): LoginRequest
{
    $request = new LoginRequest;
    $request->merge([
        'email' => $email,
        'password' => $password,
        'remember' => $remember,
    ]);
    $request->server->set('REMOTE_ADDR', $ip);

    return $request;
}

beforeEach(function () {
    // Clear all rate limiting before each test
    $testEmails = [
        'test@example.com',
        'rate-limit@example.com',
        'clear-limit@example.com',
        'no-limit@example.com',
        'minutes-test@example.com',
        'remember@example.com',
        'boundary-test@example.com',
    ];

    foreach ($testEmails as $email) {
        RateLimiter::clear($email.'|127.0.0.1');
    }
});

describe('basic functionality', function () {
    it('has correct validation rules', function () {
        $request = new LoginRequest;

        expect($request->rules())->toBe([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
    });

    it('authorizes all users', function () {
        $request = new LoginRequest;

        expect($request->authorize())->toBeTrue();
    });

    it('generates correct throttle key', function () {
        $request = createLoginRequest('Test@Example.com', 'password', false, '192.168.1.1');

        expect($request->throttleKey())->toBe('test@example.com|192.168.1.1');
    });
});

describe('authentication', function () {
    it('authenticates with valid credentials', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = createLoginRequest('test@example.com', 'password123');

        // Should not throw an exception
        $request->authenticate();

        expect(auth()->check())->toBeTrue()
            ->and(auth()->user()->email)->toBe('test@example.com');
    });

    it('throws validation exception with invalid credentials', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $request = createLoginRequest('test@example.com', 'wrong-password');

        expect(fn () => $request->authenticate())
            ->toThrow(function (ValidationException $e) {
                expect($e->errors())->toMatchArray([
                    'email' => [trans('auth.failed')],
                ]);
            })
            ->and(auth()->check())->toBeFalse();
    });

    it('handles remember option correctly', function () {
        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test with remember = true
        $request = createLoginRequest('remember@example.com', 'password123', true);
        $request->authenticate();

        expect(auth()->check())->toBeTrue()
            ->and(auth()->user()->email)->toBe('remember@example.com');

        auth()->logout();

        // Test with remember = false
        $request = createLoginRequest('remember@example.com', 'password123', false);
        $request->authenticate();

        expect(auth()->check())->toBeTrue()
            ->and(auth()->user()->email)->toBe('remember@example.com');
    });

    it('calls rate limiting check during authentication', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Pre-hit the rate limiter to exactly 4 attempts (just under the limit of 5)
        $throttleKey = 'test@example.com|127.0.0.1';
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($throttleKey);
        }

        // This should still succeed because we're at 4 attempts, not 5
        $request = createLoginRequest('test@example.com', 'password123');
        $request->authenticate();

        expect(auth()->check())->toBeTrue();
    });
});

describe('rate limiting', function () {
    it('does not rate limit when under threshold', function () {
        $request = createLoginRequest('no-limit@example.com', 'password');

        // Should not throw any exception
        $request->ensureIsNotRateLimited();

        expect(true)->toBeTrue();
    });

    it('rate limits exactly at the boundary of 5 attempts', function () {
        User::factory()->create([
            'email' => 'boundary-test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Make exactly 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $request = createLoginRequest('boundary-test@example.com', 'wrong-password');

            try {
                $request->authenticate();
            } catch (ValidationException $e) {
                // Expected - invalid credentials
            }
        }

        // The 6th attempt should be rate limited
        $request = createLoginRequest('boundary-test@example.com', 'wrong-password');

        Event::fake();

        expect(fn () => $request->authenticate())
            ->toThrow(function (ValidationException $e) {
                expect($e->errors())->toHaveKey('email');
                $errorMessage = $e->errors()['email'][0];
                expect($errorMessage)->toContain('Too many login attempts');
            });

        Event::assertDispatched(Lockout::class);
    });

    it('triggers rate limiting with exactly 5 attempts when calling ensureIsNotRateLimited directly', function () {
        $throttleKey = 'rate-limit@example.com|127.0.0.1';

        // Hit the rate limiter exactly 5 times
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        $request = createLoginRequest('rate-limit@example.com', 'password');

        Event::fake();

        expect(fn () => $request->ensureIsNotRateLimited())
            ->toThrow(function (ValidationException $e) {
                expect($e->errors())->toHaveKey('email');
                $errorMessage = $e->errors()['email'][0];
                expect($errorMessage)->toContain('Too many login attempts');
            });

        Event::assertDispatched(Lockout::class);
    });

    it('clears rate limiting after successful authentication', function () {
        User::factory()->create([
            'email' => 'clear-limit@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Make a few failed attempts first
        for ($i = 0; $i < 3; $i++) {
            $request = createLoginRequest('clear-limit@example.com', 'wrong-password');

            try {
                $request->authenticate();
            } catch (ValidationException $e) {
                // Expected - invalid credentials
            }
        }

        // Authenticate successfully - this should clear the rate limiter
        $request = createLoginRequest('clear-limit@example.com', 'correct-password');
        $request->authenticate();

        // Verify the rate limiter was cleared
        $throttleKey = $request->throttleKey();
        expect(RateLimiter::attempts($throttleKey))->toBe(0);
    });

    it('includes correct timeout in rate limiting message', function () {
        $throttleKey = 'minutes-test@example.com|127.0.0.1';

        // Trigger rate limiting by hitting 5 times
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        $request = createLoginRequest('minutes-test@example.com', 'password');

        expect(fn () => $request->ensureIsNotRateLimited())
            ->toThrow(function (ValidationException $e) {
                expect($e->errors())->toHaveKey('email');
                $errorMessage = $e->errors()['email'][0];
                expect($errorMessage)->toContain('Too many login attempts')
                    ->toMatch('/\d+ seconds/');
            });
    });
});
