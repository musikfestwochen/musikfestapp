<?php

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Sensor;
use Illuminate\Support\Collection;

class SensorService
{
    /**
     * @return Collection<int, Sensor>
     */
    public function getSensors(): Collection
    {
        $currentOrgId = getPermissionsOrgId();
        $query = Sensor::query();

        if ($currentOrgId !== GLOBAL_ORG_ID) {
            $query->where('organization_id', $currentOrgId);
        }

        return $query->get();
    }
}
