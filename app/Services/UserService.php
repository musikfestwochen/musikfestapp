<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserService
{
    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = User::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->whereHas('organizations', function (Builder $query) use ($currentOrgId) {
                $query->where('organizations.id', $currentOrgId);
            });
        }

        return $query->get();
    }
}
