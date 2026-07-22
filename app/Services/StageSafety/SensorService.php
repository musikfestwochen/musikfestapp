<?php

declare(strict_types=1);

namespace App\Services\StageSafety;

use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SensorService
{
    public const string SENSOR_TOKEN_NAME = 'stage_safety_sensor_token';

    /**
     * @return Collection<int, Sensor>
     */
    public function getSensors(Organization $organization, bool $onlyArchived = false): Collection
    {
        return $organization->stageSafetySensors()
            ->when(
                $onlyArchived,
                fn (Builder $query) => $query->whereNotNull('archived_at'),
                fn (Builder $query) => $query->whereNull('archived_at'),
            )
            ->withExists(['tokens as has_active_token'])
            ->orderBy('name')
            ->orderBy('serial')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{sensor: Sensor, token: string}
     */
    public function createWithToken(Organization $organization, array $attributes): array
    {
        return DB::transaction(function () use ($organization, $attributes): array {
            $sensor = $organization->stageSafetySensors()->create($attributes);

            return [
                'sensor' => $sensor,
                'token' => $this->issueToken($sensor),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Organization $organization, Sensor $sensor, array $attributes): Sensor
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);

        $sensor->update($attributes);

        return $sensor;
    }

    public function createOrRegenerateToken(Organization $organization, Sensor $sensor): string
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);
        $this->ensureSensorIsActive($sensor);

        return DB::transaction(function () use ($sensor): string {
            $sensor->tokens()->where('name', self::SENSOR_TOKEN_NAME)->delete();

            return $this->issueToken($sensor);
        });
    }

    public function revokeTokens(Organization $organization, Sensor $sensor): void
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);

        $sensor->tokens()->delete();
    }

    public function archive(Organization $organization, Sensor $sensor): Sensor
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);

        return DB::transaction(function () use ($sensor): Sensor {
            $sensor->tokens()->delete();
            $sensor->update(['archived_at' => Date::now()]);

            return $sensor;
        });
    }

    public function restore(Organization $organization, Sensor $sensor): Sensor
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);

        $sensor->update(['archived_at' => null]);

        return $sensor;
    }

    public function delete(Organization $organization, Sensor $sensor): void
    {
        $this->verifySensorBelongsToOrganization($organization, $sensor);

        DB::transaction(function () use ($sensor): void {
            $sensor->tokens()->delete();
            $sensor->delete();
        });
    }

    public function verifySensorBelongsToOrganization(Organization $organization, Sensor $sensor): void
    {
        throw_if(
            $sensor->organization_id !== $organization->id,
            AuthorizationException::class,
            'You are not authorized to manage this sensor.',
        );
    }

    protected function issueToken(Sensor $sensor): string
    {
        return $sensor->createToken(self::SENSOR_TOKEN_NAME)->plainTextToken;
    }

    protected function ensureSensorIsActive(Sensor $sensor): void
    {
        throw_if($sensor->archived_at !== null, ValidationException::withMessages([
            'sensor' => 'Archived sensors cannot receive API tokens.',
        ]));
    }
}
