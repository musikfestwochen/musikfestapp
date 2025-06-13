<?php

use App\Http\Requests\OrganizationSelectionRequest;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('authorizes all authenticated users', function () {
    // Create a request instance
    $request = new OrganizationSelectionRequest;

    // Assert that the authorize method returns true
    expect($request->authorize())->toBeTrue();
});

it('validates that organization_id is required', function () {
    // Create a validator with empty data
    $validator = Validator::make(
        [],
        (new OrganizationSelectionRequest)->rules()
    );

    // Assert that the validator fails
    expect($validator->fails())->toBeTrue();

    // Assert that the organization_id field is required
    expect($validator->errors()->has('organization_id'))->toBeTrue();
});

it('validates that organization_id exists in the database', function () {
    // Create a validator with a non-existent organization ID
    $validator = Validator::make(
        ['organization_id' => 999],
        (new OrganizationSelectionRequest)->rules()
    );

    // Assert that the validator fails
    expect($validator->fails())->toBeTrue();

    // Assert that the organization_id field has an error
    expect($validator->errors()->has('organization_id'))->toBeTrue();
});

it('allows GLOBAL_ORG_ID as a valid organization_id', function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Create a validator with GLOBAL_ORG_ID
    $validator = Validator::make(
        ['organization_id' => GLOBAL_ORG_ID],
        (new OrganizationSelectionRequest)->rules()
    );

    // Assert that the validator passes
    expect($validator->passes())->toBeTrue();
});

it('allows existing organization_id as valid', function () {
    // Create an organization
    $organization = Organization::factory()->create();

    // Create a validator with the organization ID
    $validator = Validator::make(
        ['organization_id' => $organization->id],
        (new OrganizationSelectionRequest)->rules()
    );

    // Assert that the validator passes
    expect($validator->passes())->toBeTrue();
});
