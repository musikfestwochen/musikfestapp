<?php

use App\Http\Requests\OrganizationSelectionRequest;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

covers(OrganizationSelectionRequest::class);

beforeEach(function () {
    $this->request = new OrganizationSelectionRequest;
});

it('has correct rules', function () {
    // Create some organizations for testing (using make() not create())
    $org1 = Organization::factory()->make(['id' => 1]);
    $org2 = Organization::factory()->make(['id' => 2]);

    // But for this test to work properly, we need actual database data
    // So let's create them in the database for integration testing
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $rules = $this->request->rules();

    expect($rules)->toHaveKey('organization_id');
    expect($rules['organization_id'])->toContain('required');

    // Check that there's a Rule::in constraint
    $hasInRule = collect($rules['organization_id'])->contains(function ($rule): bool {
        return is_object($rule) && method_exists($rule, '__toString');
    });

    expect($hasInRule)->toBeTrue('Should have a Rule::in constraint for organization_id');
});

it('authorizes all authenticated users', function () {
    expect($this->request->authorize())->toBeTrue();
});

it('validates organization_id is required', function () {
    $validator = validator([], $this->request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('organization_id'))->toBeTrue();
});

it('validates organization_id exists in database', function () {
    $organization = Organization::factory()->create();

    $validator = validator([
        'organization_id' => $organization->id,
    ], $this->request->rules());

    expect($validator->passes())->toBeTrue();
});

it('validates GLOBAL_ORG_ID is allowed', function () {
    $validator = validator([
        'organization_id' => GLOBAL_ORG_ID,
    ], $this->request->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects non-existent organization_id', function () {
    $validator = validator([
        'organization_id' => 999999,
    ], $this->request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('organization_id'))->toBeTrue();
});

it('rejects invalid organization_id types', function () {
    $validator = validator([
        'organization_id' => 'invalid',
    ], $this->request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('organization_id'))->toBeTrue();
});

it('has required methods', function () {
    expect(method_exists($this->request, 'rules'))->toBeTrue();
    expect(method_exists($this->request, 'authorize'))->toBeTrue();
    expect(method_exists($this->request, 'casts'))->toBeTrue();
});

it('has correct casts', function () {
    expect($this->request->casts())->toBe([
        'organization_id' => 'integer',
    ]);
});

it('extends FormRequest', function () {
    // Test that the request properly extends FormRequest
    expect($this->request)->toBeInstanceOf(FormRequest::class);
});
