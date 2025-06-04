<?php

use App\Http\Requests\Admin\OrganizationUpdateRequest;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(OrganizationUpdateRequest::class);

test('authorize returns true when user is authenticated', function () {
    mockAuth(true);

    $request = new OrganizationUpdateRequest;
    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when user is not authenticated', function () {
    mockAuth(false);

    $request = new OrganizationUpdateRequest;
    expect($request->authorize())->toBeFalse();
});

test('rules returns expected validation rules with organization ID', function () {
    // Create a mock organization
    $organization = new Organization;
    $organization->id = 1;

    // Create the request and set the organization
    $request = new OrganizationUpdateRequest;
    $request->setRouteResolver(function () use ($organization) {
        return new class($organization)
        {
            protected $organization;

            public function __construct($organization)
            {
                $this->organization = $organization;
            }

            public function parameter($name)
            {
                return $name === 'organization' ? $this->organization : null;
            }
        };
    });

    $rules = $request->rules();

    expect($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('slug')
        ->and($rules)->toHaveKey('description')
        ->and($rules)->toHaveKey('email')
        ->and($rules)->toHaveKey('phone')
        ->and($rules)->toHaveKey('website')
        ->and($rules)->toHaveKey('logo')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['name'])->toContain('max:255')
        ->and($rules['name'])->toContain('unique:organizations,name,1')
        ->and($rules['slug'])->toContain('required')
        ->and($rules['slug'])->toContain('string')
        ->and($rules['slug'])->toContain('max:255')
        ->and($rules['slug'])->toContain('unique:organizations,slug,1')
        ->and($rules['description'])->toContain('nullable')
        ->and($rules['description'])->toContain('string')
        ->and($rules['email'])->toContain('nullable')
        ->and($rules['email'])->toContain('string')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255')
        ->and($rules['phone'])->toContain('nullable')
        ->and($rules['phone'])->toContain('string')
        ->and($rules['phone'])->toContain('max:255')
        ->and($rules['website'])->toContain('nullable')
        ->and($rules['website'])->toContain('string')
        ->and($rules['website'])->toContain('max:255')
        ->and($rules['logo'])->toContain('nullable')
        ->and($rules['logo'])->toContain('string')
        ->and($rules['logo'])->toContain('max:255');
});

test('rules returns expected validation rules with null organization', function () {
    // Create the request and set the organization to null
    $request = new OrganizationUpdateRequest;
    $request->setRouteResolver(function () {
        return new class
        {
            public function parameter($name)
            {
                return null;
            }
        };
    });

    $rules = $request->rules();

    expect($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('slug')
        ->and($rules)->toHaveKey('description')
        ->and($rules)->toHaveKey('email')
        ->and($rules)->toHaveKey('phone')
        ->and($rules)->toHaveKey('website')
        ->and($rules)->toHaveKey('logo')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['name'])->toContain('max:255')
        ->and($rules['name'])->toContain('unique:organizations,name,')
        ->and($rules['slug'])->toContain('required')
        ->and($rules['slug'])->toContain('string')
        ->and($rules['slug'])->toContain('max:255')
        ->and($rules['slug'])->toContain('unique:organizations,slug,')
        ->and($rules['description'])->toContain('nullable')
        ->and($rules['description'])->toContain('string')
        ->and($rules['email'])->toContain('nullable')
        ->and($rules['email'])->toContain('string')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255')
        ->and($rules['phone'])->toContain('nullable')
        ->and($rules['phone'])->toContain('string')
        ->and($rules['phone'])->toContain('max:255')
        ->and($rules['website'])->toContain('nullable')
        ->and($rules['website'])->toContain('string')
        ->and($rules['website'])->toContain('max:255')
        ->and($rules['logo'])->toContain('nullable')
        ->and($rules['logo'])->toContain('string')
        ->and($rules['logo'])->toContain('max:255');

    // If the test fails, output the actual values for debugging
    if (! str_contains($rules['name'], 'unique:organizations,name,')) {
        test()->fail('Expected name unique rule to contain empty string. Actual value: '.$rules['name']);
    }
    if (! str_contains($rules['slug'], 'unique:organizations,slug,')) {
        test()->fail('Expected slug unique rule to contain empty string. Actual value: '.$rules['slug']);
    }
});
