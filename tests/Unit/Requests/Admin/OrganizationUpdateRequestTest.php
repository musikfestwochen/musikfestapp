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

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255', 'unique:organizations,name,1'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,1'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
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

    // Define expected rules
    $expectedRules = [
        'name' => ['required', 'string', 'max:255', 'unique:organizations,name,'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ];

    // Assert that the rules match the expected rules
    expect($rules)->toEqualCanonicalizing($expectedRules);
});
