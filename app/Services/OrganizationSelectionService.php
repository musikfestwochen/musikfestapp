<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class OrganizationSelectionService
{
    /**
     * Get organizations available for the current user.
     */
    public function getOrganizationsForUser(): Collection
    {
        $user = Auth::user();

        if ($user->can('admin.organizations.index')) {
            $organizations = Organization::select('id', 'name', 'slug')->get();

            // add an "Administration" option for admins
            $organizations->prepend((object) [
                'id' => GLOBAL_ORG_ID,
                'name' => 'Administration',
                'slug' => 'admin',
            ]);
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

        $organization = Organization::findOrFail($organizationId);
        $user = Auth::user();

        // Check if the user belongs to the selected organization
        if (! $user->organizations->contains($organization->id) && ! $user->can('admin.organizations.index')) {
            throw new AuthorizationException('You do not have access to this organization.');
        }

        return $organization->slug;
    }
}
