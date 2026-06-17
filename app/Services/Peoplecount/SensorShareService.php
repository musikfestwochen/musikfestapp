<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

class SensorShareService
{
    /**
     * @return Collection<int, SensorShare>
     */
    public function getLentShares(Organization $organization): Collection
    {
        return $organization->lentSensorShares()
            ->with(['sensor', 'borrowerOrganization'])
            ->latest('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, SensorShare>
     */
    public function getBorrowedShares(Organization $organization): Collection
    {
        return $organization->borrowedSensorShares()
            ->with(['sensor.organization', 'ownerOrganization'])
            ->latest('starts_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SensorShare
    {
        $sensor = Sensor::query()->whereKey((int) $attributes['sensor_id'])->firstOrFail();
        $this->verifySensorOwnedByCurrentOrganization($sensor);
        $this->verifyBorrowerIsDifferentOrganization((int) $attributes['borrower_organization_id'], $sensor->organization_id);

        return SensorShare::query()->create([
            'sensor_id' => $sensor->id,
            'owner_organization_id' => $sensor->organization_id,
            'borrower_organization_id' => $attributes['borrower_organization_id'],
            'created_by' => $attributes['created_by'] ?? auth()->id(),
            'starts_at' => $attributes['starts_at'],
            'ends_at' => $attributes['ends_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SensorShare $sensorShare, array $attributes): SensorShare
    {
        $this->verifyShareOwnedByCurrentOrganization($sensorShare);
        $this->verifyBorrowerCanBeChanged($sensorShare, (int) ($attributes['borrower_organization_id'] ?? $sensorShare->borrower_organization_id));
        $this->verifyShareWindowContainsAssignments(
            $sensorShare,
            Date::parse($attributes['starts_at']),
            Date::parse($attributes['ends_at'])
        );

        $sensorShare->update([
            'borrower_organization_id' => $attributes['borrower_organization_id'] ?? $sensorShare->borrower_organization_id,
            'starts_at' => $attributes['starts_at'],
            'ends_at' => $attributes['ends_at'],
        ]);

        return $sensorShare;
    }

    public function delete(SensorShare $sensorShare): void
    {
        $this->verifyShareOwnedByCurrentOrganization($sensorShare);

        throw_if($sensorShare->assignments()->exists(), ValidationException::withMessages([
            'sensor_share_id' => 'This sensor share cannot be deleted because it is used by assignments.',
        ]));

        $sensorShare->delete();
    }

    public function findValidShareForAssignment(Sensor $sensor, Event $event, string $activeFrom, string $activeTo): ?SensorShare
    {
        return SensorShare::query()
            ->where('sensor_id', $sensor->id)
            ->where('borrower_organization_id', $event->organization_id)
            ->where('starts_at', '<=', $activeFrom)
            ->where('ends_at', '>=', $activeTo)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @throws AuthorizationException
     */
    public function verifySensorOwnedByCurrentOrganization(Sensor $sensor): void
    {
        $currentOrgId = getPermissionsOrgId();

        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        throw_if(
            $sensor->organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to share this sensor.'
        );
    }

    /**
     * @throws AuthorizationException
     */
    protected function verifyShareOwnedByCurrentOrganization(SensorShare $sensorShare): void
    {
        $currentOrgId = getPermissionsOrgId();

        if ($currentOrgId === GLOBAL_ORG_ID) {
            return;
        }

        throw_if(
            $sensorShare->owner_organization_id !== $currentOrgId,
            AuthorizationException::class,
            'You are not authorized to manage this sensor share.'
        );
    }

    protected function verifyBorrowerIsDifferentOrganization(int $borrowerOrganizationId, int $ownerOrganizationId): void
    {
        throw_if($borrowerOrganizationId === $ownerOrganizationId, ValidationException::withMessages([
            'borrower_organization_id' => 'A sensor can only be shared with another organization.',
        ]));
    }

    protected function verifyShareWindowContainsAssignments(SensorShare $sensorShare, Carbon $startsAt, Carbon $endsAt): void
    {
        $minActiveFrom = $sensorShare->assignments()->min('active_from');
        $maxActiveTo = $sensorShare->assignments()->max('active_to');

        if ($minActiveFrom === null || $maxActiveTo === null) {
            return;
        }

        throw_if(
            $startsAt->greaterThan(Date::parse($minActiveFrom)) || $endsAt->lessThan(Date::parse($maxActiveTo)),
            ValidationException::withMessages([
                'starts_at' => 'The share period must include all assignments that use this share.',
                'ends_at' => 'The share period must include all assignments that use this share.',
            ])
        );
    }

    protected function verifyBorrowerCanBeChanged(SensorShare $sensorShare, int $borrowerOrganizationId): void
    {
        if ($borrowerOrganizationId === $sensorShare->borrower_organization_id) {
            return;
        }

        throw_if($sensorShare->assignments()->exists(), ValidationException::withMessages([
            'borrower_organization_id' => 'The borrowing organization cannot be changed while assignments use this share.',
        ]));

        $this->verifyBorrowerIsDifferentOrganization($borrowerOrganizationId, $sensorShare->owner_organization_id);
    }
}
