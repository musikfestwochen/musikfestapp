<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function getPaginatedUsers(string $sort = 'name', string $direction = 'asc'): LengthAwarePaginator
    {
        $currentOrgId = getPermissionsOrgId();
        $query = User::query();

        if ($currentOrgId != GLOBAL_ORG_ID) {
            $query->whereHas('organizations', function ($query) use ($currentOrgId) {
                $query->where('organizations.id', $currentOrgId);
            });
        }

        if (in_array($sort, ['name', 'email', 'created_at'])) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate(10)->withQueryString();
    }
}
