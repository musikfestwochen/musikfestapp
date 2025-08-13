<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        if ($organization instanceof \App\Models\Organization) {
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
}
