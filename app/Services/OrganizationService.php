<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationService
{
    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    public function getPaginatedOrganizations(string $sort = 'name', string $direction = 'asc'): LengthAwarePaginator
    {
        $query = Organization::query();

        if (in_array($sort, ['name', 'email', 'website', 'created_at'])) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate(10)->withQueryString();
    }
}
