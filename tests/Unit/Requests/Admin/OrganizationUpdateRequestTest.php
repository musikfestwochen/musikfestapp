<?php

use App\Http\Requests\Admin\OrganizationUpdateRequest;
use App\Models\Organization;
use App\Models\User;

covers(OrganizationUpdateRequest::class);

beforeEach(function () {
    $this->request = new OrganizationUpdateRequest;
});

it('authorizes when user can update organizations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.organizations.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('has correct rules with organization ID', function () {
    // Create a mock organization
    $organization = new Organization;
    $organization->id = 1;

    // Create the request and set the organization
    $request = new OrganizationUpdateRequest;
    $request->setRouteResolver(function () use ($organization): object {
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

    expect($request->rules())->toBe([
        'name' => ['required', 'string', 'max:255', 'unique:organizations,name,1'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,1'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ]);
});

it('has correct rules with null organization', function () {
    // Create the request and set the organization to null
    $request = new OrganizationUpdateRequest;
    $request->setRouteResolver(function (): object {
        return new class
        {
            public function parameter($name): null
            {
                return null;
            }
        };
    });

    expect($request->rules())->toBe([
        'name' => ['required', 'string', 'max:255', 'unique:organizations,name,'],
        'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'],
        'description' => ['nullable', 'string'],
        'email' => ['nullable', 'string', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'website' => ['nullable', 'string', 'max:255'],
        'logo' => ['nullable', 'string', 'max:255'],
    ]);
});
