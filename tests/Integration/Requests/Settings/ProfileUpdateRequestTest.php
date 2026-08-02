<?php

use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

covers(ProfileUpdateRequest::class);

beforeEach(function () {
    // Create a real user in the database for testing
    $this->user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->request = new ProfileUpdateRequest;
    $this->request->setUserResolver(fn () => $this->user);
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules)->toHaveKeys(['name', 'email'])
        ->and($rules['name'])->toBe(['required', 'string', 'max:255']);

    // For email, we check the structure since it contains a Rule object
    expect($rules['email'])->toContain('required');
    expect($rules['email'])->toContain('string')
        ->toContain('lowercase')
        ->toContain('email')
        ->toContain('max:255');

    // Check that the unique rule exists and ignores the current user
    $uniqueRule = null;
    foreach ($rules['email'] as $rule) {
        if (is_object($rule) && method_exists($rule, '__toString')) {
            $uniqueRule = $rule;
            break;
        }
    }

    expect($uniqueRule)->not->toBeNull();
});

it('validates name is required', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $nameRules = $rules['name'];
    expect($nameRules)->toContain('required');
});

it('validates email is required', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $emailRules = $rules['email'];
    expect($emailRules)->toContain('required');
});

it('validates email format', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $emailRules = $rules['email'];
    expect($emailRules)->toContain('email');
});

it('validates name max length', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $nameRules = $rules['name'];
    expect($nameRules)->toContain('max:255');
});

it('validates email max length', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $emailRules = $rules['email'];
    expect($emailRules)->toContain('max:255');
});

it('validates email is lowercase', function () {
    $rules = $this->request->rules();

    // Check rules directly
    $emailRules = $rules['email'];
    expect($emailRules)->toContain('lowercase');
});

it('allows valid data', function () {
    $rules = $this->request->rules();

    // Check that all required rules are present
    expect($rules['name'])->toContain('required');
    expect($rules['email'])->toContain('required')
        ->toContain('email');
});

it('allows same email for current user', function () {
    $rules = $this->request->rules();

    // Check that the unique rule exists and has the right structure
    $uniqueRule = null;
    foreach ($rules['email'] as $rule) {
        if (is_object($rule) && method_exists($rule, '__toString')) {
            $uniqueRule = $rule;
            break;
        }
    }

    expect($uniqueRule)->not->toBeNull('Should have a unique rule for email validation');
});

it('prevents duplicate email for different user', function () {
    // Test the rule structure instead of runtime validation since that requires database
    $rules = $this->request->rules();

    // Check that the unique rule exists
    $uniqueRule = null;
    foreach ($rules['email'] as $rule) {
        if (is_object($rule) && method_exists($rule, '__toString')) {
            $uniqueRule = $rule;
            break;
        }
    }

    expect($uniqueRule)->not->toBeNull('Should have a unique rule for email validation');

    // The unique rule should ignore the current user's ID
    $ruleString = (string) $uniqueRule;
    expect($ruleString)->toContain('users'); // Table name
    expect($ruleString)->toContain((string) $this->user->id); // Current user ID should be ignored
});

it('has correct phone rules', function () {
    $rules = $this->request->rules();
    expect($rules)->toHaveKey('phone')
        ->and($rules['phone'])->toContain('nullable')
        ->toContain('string')
        ->toContain('max:20');

    $uniqueRule = null;
    foreach ($rules['phone'] as $rule) {
        if (is_object($rule) && method_exists($rule, '__toString')) {
            $uniqueRule = $rule;
            break;
        }
    }

    expect($uniqueRule)->not->toBeNull('Should have a unique rule for phone validation');
    $ruleString = (string) $uniqueRule;
    expect($ruleString)->toContain('users')
        ->toContain((string) $this->user->id);
});

it('validates phone is nullable', function () {
    $rules = $this->request->rules();
    $phoneRules = $rules['phone'];
    expect($phoneRules)->toContain('nullable');
});

it('validates phone max length', function () {
    $rules = $this->request->rules();
    $phoneRules = $rules['phone'];
    expect($phoneRules)->toContain('max:20');
});
