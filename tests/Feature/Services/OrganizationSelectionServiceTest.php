<?php

use App\Models\Organization;
use App\Services\OrganizationSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }
});

it('returns admin slug when selecting GLOBAL_ORG_ID', function () {
    // Create the service
    $service = new OrganizationSelectionService;

    // Process the organization selection with GLOBAL_ORG_ID
    $result = $service->processOrganizationSelection(GLOBAL_ORG_ID);

    // Assert that the result is 'admin'
    expect($result)->toBe('admin');
});
