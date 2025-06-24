<?php

namespace App\Services\Peoplecount;

use App\Models\Peoplecount\Sensor;
use Illuminate\Support\Collection;

class SensorService
{
    const string SENSOR_TOKEN_NAME = 'peoplecount_sensor_token';

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

    /**
     * Create a new sensor and generate its API token.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithToken(array $attributes): Sensor
    {
        $sensor = Sensor::query()->create($attributes);
        $token = $this->createOrRegenerateToken($sensor);
        $sensor->api_token = $token;
        $sensor->save();

        return $sensor;
    }

    /**
     * Create or regenerate the peoplecount_sensor_token for a sensor.
     * Deletes any existing token with the same name before creating a new one.
     *
     * @return string The plain text token
     */
    public function createOrRegenerateToken(Sensor $sensor): string
    {
        // Delete existing token(s) with the same name
        $sensor->tokens()->where('name', self::SENSOR_TOKEN_NAME)->delete();
        // Create new token and return plain text
        $token = $sensor->createToken(self::SENSOR_TOKEN_NAME);

        // TODO: Storing token in plaintext, revisit if API becomes sensitive
        return $token->plainTextToken;
    }
}
