<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Collection;

class OrganizationService
{
    /**
     * @return Collection<int, Organization>
     */
    public function getOrganizations(): Collection
    {
        return Organization::query()->get();
    }
}
