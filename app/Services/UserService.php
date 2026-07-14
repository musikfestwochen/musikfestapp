<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * @return array<int, array{name: string, display_name: string|null, description: string|null}>
     */
    public function availableOrganizationRoles(): array
    {
        $roleNames = ['PeopleCountViewer', 'OrganizationAdmin'];
        $roles = Role::query()
            ->whereIn('name', $roleNames)
            ->get(['name', 'display_name', 'description'])
            ->keyBy('name');

        $availableRoles = [];

        foreach ($roleNames as $roleName) {
            $role = $roles->get($roleName);

            if (! $role instanceof Role) {
                continue;
            }

            $displayName = $role->getAttribute('display_name');
            $description = $role->getAttribute('description');

            $availableRoles[] = [
                'name' => $roleName,
                'display_name' => is_string($displayName) ? $displayName : null,
                'description' => is_string($description) ? $description : null,
            ];
        }

        return $availableRoles;
    }

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
     * @param  array{name: string, email: string, phone?: string|null}  $data
     * @param  array<int, string>  $roleNames
     */
    public function createOrAttachToOrganization(Organization $organization, array $data, array $roleNames = ['PeopleCountViewer']): User
    {
        return DB::transaction(function () use ($organization, $data, $roleNames): User {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::random(),
                ],
            );

            $user->organizations()->syncWithoutDetaching([$organization->id]);
            $this->syncOrganizationRoles($user, $organization, $roleNames);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     * @param  array<int, string>|null  $roleNames
     */
    public function updateForOrganization(Organization $organization, User $user, array $data, ?array $roleNames): void
    {
        DB::transaction(function () use ($organization, $user, $data, $roleNames): void {
            $user->update($data);

            if ($roleNames !== null) {
                $this->syncOrganizationRoles($user, $organization, $roleNames);
            }
        });
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    public function syncOrganizationRoles(User $user, Organization $organization, array $roleNames): void
    {
        $previousOrganizationId = getPermissionsOrgId();

        try {
            setPermissionsOrgId($organization->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles($roleNames);
        } finally {
            setPermissionsOrgId($previousOrganizationId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * @return array<int, string>
     */
    public function getOrganizationRoleNames(User $user, Organization $organization): array
    {
        $previousOrganizationId = getPermissionsOrgId();

        try {
            setPermissionsOrgId($organization->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->getRoleNames()->values()->all();
        } finally {
            setPermissionsOrgId($previousOrganizationId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
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
}
