<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Fetch users. When an Organization is provided, limit to that org. Optionally select specific columns.
     * Otherwise, fall back to current permissions org context.
     *
     * @param  array<int, string>|null  $columns  Columns to select (e.g., ['users.id','users.name','users.email']). When null, selects all.
     * @return Collection<int, User>
     */
    public function getUsers(?Organization $organization = null, ?array $columns = null): Collection
    {
        $query = User::query();

        if ($organization instanceof Organization) {
            $query->whereHas('organizations', function (Builder $query) use ($organization) {
                $query->where('organizations.id', $organization->id);
            });

            if ($columns !== null) {
                $query->select($columns);
            }

            return $query->get();
        }

        // Fallback: use current permissions org context
        $currentOrgId = getPermissionsOrgId();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->whereHas('organizations', function (Builder $query) use ($currentOrgId) {
                $query->where('organizations.id', $currentOrgId);
            });
        }

        if ($columns !== null) {
            $query->select($columns);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsersWithOrganizationCount(): Collection
    {
        return User::query()->withCount('organizations')->get();
    }

    /**
     * Create a user or attach an existing user to an organization.
     *
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function createOrAttachToOrganization(Organization $organization, array $data): User
    {
        return DB::transaction(function () use ($organization, $data): User {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::random(),
                ],
            );

            $user->organizations()->syncWithoutDetaching([$organization->id]);
            $this->assignDefaultOrganizationRole($user, $organization);

            return $user;
        });
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    public function syncOrganizations(User $user, array $organizationIds): void
    {
        $currentOrganizationIds = $user->organizations()->pluck('organizations.id')->all();
        $removedOrganizationIds = array_diff($currentOrganizationIds, $organizationIds);

        foreach ($removedOrganizationIds as $organizationId) {
            $this->removeOrganizationAccess($user, Organization::query()->whereKey($organizationId)->firstOrFail());
        }

        $user->organizations()->sync($organizationIds);
    }

    /**
     * Remove a user from an organization.
     *
     * @return bool True when the user was deleted, false when only detached.
     */
    public function removeFromOrganization(User $user, Organization $organization): bool
    {
        $organizationCount = $user->organizations()->count();

        $this->removeOrganizationAccess($user, $organization);

        if ($organizationCount === 1) {
            $user->delete();

            return true;
        }

        $user->organizations()->detach($organization->id);

        return false;
    }

    protected function removeOrganizationAccess(User $user, Organization $organization): void
    {
        $previousOrganizationId = getPermissionsOrgId();

        try {
            setPermissionsOrgId($organization->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([]);
            $user->syncPermissions([]);
        } finally {
            setPermissionsOrgId($previousOrganizationId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    protected function assignDefaultOrganizationRole(User $user, Organization $organization): void
    {
        $previousOrganizationId = getPermissionsOrgId();

        try {
            setPermissionsOrgId($organization->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->assignRole('PeopleCountViewer');
        } finally {
            setPermissionsOrgId($previousOrganizationId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
