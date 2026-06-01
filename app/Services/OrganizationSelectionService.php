<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class OrganizationSelectionService
{
    /**
     * Get organizations available for the current user.
     *
     * @return Collection<int, Organization>
     */
    public function getOrganizationsForUser(): Collection
    {
        $user = Auth::user();

        if ($user->can('admin.organizations.index')) {
            $organizations = Organization::query()->select('id', 'name', 'slug')->get();

            // add an "Administration" option for admins
            $adminOrg = new Organization([
                'name' => 'Administration',
                'slug' => 'admin',
            ]);
            $adminOrg->id = GLOBAL_ORG_ID;
            $organizations->prepend($adminOrg);
        } else {
            $organizations = $user->organizations()->select('organizations.id', 'organizations.name', 'organizations.slug')->get();
        }

        return $organizations;
    }

    /**
     * Process the organization selection.
     *
     * @throws AuthorizationException
     */
    public function processOrganizationSelection(int $organizationId): string
    {
        // if organizationId is GLOBAL_ORG_ID, redirect to the admin dashboard
        if ($organizationId === GLOBAL_ORG_ID) {
            return 'admin';
        }

        $organization = Organization::query()->findOrFail($organizationId);
        $user = Auth::user();

        // Check if the user belongs to the selected organization
        throw_if(! $user->organizations->contains($organization->id) && ! $user->can('admin.organizations.index'), AuthorizationException::class, 'You do not have access to this organization.');

        return $organization->slug;
    }
}
