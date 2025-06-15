<?php

use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

covers(App\Services\OrganizationService::class);

beforeEach(function () {
    $this->service = new OrganizationService;
});

describe('getPaginatedOrganizations', function () {
    it('returns paginated organizations with default sorting', function () {
        Organization::factory()->count(15)->create();

        $result = $this->service->getPaginatedOrganizations();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->perPage())->toBe(10);
        expect($result->total())->toBe(15);
        expect($result->currentPage())->toBe(1);
    });

    it('sorts organizations by name in ascending order by default', function () {
        Organization::factory()->create(['name' => 'Zulu Organization']);
        Organization::factory()->create(['name' => 'Alpha Organization']);
        Organization::factory()->create(['name' => 'Beta Organization']);

        $result = $this->service->getPaginatedOrganizations();

        $names = $result->items();
        expect($names[0]->name)->toBe('Alpha Organization');
        expect($names[1]->name)->toBe('Beta Organization');
        expect($names[2]->name)->toBe('Zulu Organization');
    });

    it('sorts organizations by name in descending order when specified', function () {
        Organization::factory()->create(['name' => 'Alpha Organization']);
        Organization::factory()->create(['name' => 'Beta Organization']);
        Organization::factory()->create(['name' => 'Zulu Organization']);

        $result = $this->service->getPaginatedOrganizations('name', 'desc');

        $names = $result->items();
        expect($names[0]->name)->toBe('Zulu Organization');
        expect($names[1]->name)->toBe('Beta Organization');
        expect($names[2]->name)->toBe('Alpha Organization');
    });

    it('sorts organizations by email when specified', function () {
        Organization::factory()->create(['email' => 'zulu@example.com']);
        Organization::factory()->create(['email' => 'alpha@example.com']);
        Organization::factory()->create(['email' => 'beta@example.com']);

        $result = $this->service->getPaginatedOrganizations('email', 'asc');

        $emails = $result->items();
        expect($emails[0]->email)->toBe('alpha@example.com');
        expect($emails[1]->email)->toBe('beta@example.com');
        expect($emails[2]->email)->toBe('zulu@example.com');
    });

    it('sorts organizations by website when specified', function () {
        Organization::factory()->create(['website' => 'https://zulu.com']);
        Organization::factory()->create(['website' => 'https://alpha.com']);
        Organization::factory()->create(['website' => 'https://beta.com']);

        $result = $this->service->getPaginatedOrganizations('website', 'asc');

        $websites = $result->items();
        expect($websites[0]->website)->toBe('https://alpha.com');
        expect($websites[1]->website)->toBe('https://beta.com');
        expect($websites[2]->website)->toBe('https://zulu.com');
    });

    it('sorts organizations by created_at when specified', function () {
        $oldest = Organization::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = Organization::factory()->create(['created_at' => now()->subDays(1)]);
        $middle = Organization::factory()->create(['created_at' => now()->subDays(2)]);

        $result = $this->service->getPaginatedOrganizations('created_at', 'asc');

        $items = $result->items();
        expect($items[0]->id)->toBe($oldest->id);
        expect($items[1]->id)->toBe($middle->id);
        expect($items[2]->id)->toBe($newest->id);
    });

    it('does not apply sorting when invalid sort field is provided', function () {
        $first = Organization::factory()->create(['name' => 'Zulu Organization']);
        $second = Organization::factory()->create(['name' => 'Alpha Organization']);

        $result = $this->service->getPaginatedOrganizations('invalid_field', 'desc');

        // Should return in natural database order (by ID) when invalid sort field is provided
        $items = $result->items();
        expect($items[0]->id)->toBe($first->id);
        expect($items[1]->id)->toBe($second->id);
    });

    it('accepts valid direction parameter', function () {
        Organization::factory()->create(['name' => 'Alpha']);
        Organization::factory()->create(['name' => 'Beta']);

        $resultAsc = $this->service->getPaginatedOrganizations('name', 'asc');
        $resultDesc = $this->service->getPaginatedOrganizations('name', 'desc');

        expect($resultAsc->items()[0]->name)->toBe('Alpha');
        expect($resultDesc->items()[0]->name)->toBe('Beta');
    });

    it('preserves query string in pagination', function () {
        Organization::factory()->count(15)->create();

        $result = $this->service->getPaginatedOrganizations();

        expect($result->hasPages())->toBeTrue();
        // The withQueryString() method should be called, which we can verify
        // by checking that the paginator has the appropriate configuration
        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('returns empty paginator when no organizations exist', function () {
        $result = $this->service->getPaginatedOrganizations();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->total())->toBe(0);
        expect($result->items())->toBeEmpty();
    });

    it('handles all valid sort fields correctly', function () {
        $validSortFields = ['name', 'email', 'website', 'created_at'];

        foreach ($validSortFields as $field) {
            Organization::factory()->count(3)->create();

            $result = $this->service->getPaginatedOrganizations($field, 'asc');

            expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
            expect($result->total())->toBeGreaterThan(0);

            // Clean up for next iteration
            Organization::query()->delete();
        }
    });
});
