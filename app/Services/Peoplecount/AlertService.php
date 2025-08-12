<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class AlertService
{
    private const ALERT_RELATIONS = [
        'creator',
        'recipients',
    ];

    /**
     * Get all alerts for a given area, ensuring the area belongs to the organization context.
     *
     * @return Collection<int, Alert>
     *
     * @throws AuthorizationException
     */
    public function getAreaAlerts(Organization $organization, Area $area): Collection
    {
        $this->assertAreaBelongsToOrganization($organization, $area);

        return $area->alerts()
            ->with(self::ALERT_RELATIONS)
            ->get();
    }

    /**
     * Ensure the area belongs to the given organization.
     *
     * @throws AuthorizationException
     */
    protected function assertAreaBelongsToOrganization(Organization $organization, Area $area): void
    {
        // Area belongs to an Event which holds the organization_id
        $event = $area->event;
        throw_if($event === null || $event->organization_id !== $organization->id, new AuthorizationException('Area does not belong to the current organization.'));
    }

    /**
     * Store a newly created alert for the given area.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function storeAreaAlert(Organization $organization, Area $area, array $attributes): Alert
    {
        $this->assertAreaBelongsToOrganization($organization, $area);

        // Force the area_id to the route-bound Area to avoid tampering
        $attributes['area_id'] = $area->id;
        $attributes['created_by'] = auth()->id();

        /** @var Alert $alert */
        $alert = Alert::query()->create($attributes);

        $this->applyRecipientsFromAttributes($organization, $alert, $attributes);

        return $alert;
    }

    /**
     * Apply recipients from the attributes payload to the alert.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    protected function applyRecipientsFromAttributes(Organization $organization, Alert $alert, array $attributes): void
    {
        if (! array_key_exists('recipients', $attributes)) {
            return; // Nothing to do
        }

        $recipientIds = $this->extractRecipientIds($attributes['recipients'] ?? null);
        $this->syncRecipientsEnsuringOrgMembership($organization, $alert, $recipientIds);
    }

    /**
     * Normalize recipients field into a list of unique integer user IDs.
     *
     * @return list<int>
     */
    protected function extractRecipientIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            if (preg_match('/^\s*\[.*\]\s*$/', $value) === 1) {
                $decoded = json_decode($value);
                $value = is_array($decoded) ? $decoded : [];
            } elseif (str_contains($value, ',')) {
                $value = preg_split('/\s*,\s*/', $value) ?: [];
            } else {
                $value = [$value];
            }
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $ids = [];
        foreach ($value as $v) {
            if ($v instanceof BackedEnum) {
                $v = $v->value;
            }

            if (is_object($v) && property_exists($v, 'id')) {
                $v = $v->id;
            }

            $int = filter_var($v, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            if (is_int($int) && $int > 0) {
                $ids[$int] = $int; // use associative keys to deduplicate
            }
        }

        return array_keys($ids);
    }

    /**
     * Sync recipients, ensuring all belong to the given organization.
     *
     * @param  list<int>  $recipientIds
     *
     * @throws AuthorizationException
     */
    protected function syncRecipientsEnsuringOrgMembership(Organization $organization, Alert $alert, array $recipientIds): void
    {
        // Always sync (including empty) to reflect current selection
        if ($recipientIds === []) {
            $alert->recipients()->sync([]);

            return;
        }

        $allowedIds = $organization->users()->pluck('users.id')->all();
        $invalid = array_diff($recipientIds, $allowedIds);
        throw_unless($invalid === [], new AuthorizationException('One or more recipients do not belong to the current organization.'));

        $alert->recipients()->sync($recipientIds);
    }

    /**
     * Update an existing alert.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function updateAreaAlert(Organization $organization, Area $area, Alert $alert, array $attributes): Alert
    {
        $this->assertAlertBelongsToAreaAndOrganization($organization, $area, $alert);

        // Force the area_id to remain consistent with the route-bound Area
        $attributes['area_id'] = $area->id;

        $alert->fill($attributes);
        $alert->save();

        $this->applyRecipientsFromAttributes($organization, $alert, $attributes);

        return $alert;
    }

    /**
     * Ensure the alert belongs to the given area and organization.
     *
     * @throws AuthorizationException
     */
    protected function assertAlertBelongsToAreaAndOrganization(Organization $organization, Area $area, Alert $alert): void
    {
        throw_if($alert->area_id !== $area->id, new AuthorizationException('Alert does not belong to the specified area.'));

        $this->assertAreaBelongsToOrganization($organization, $area);
    }

    /**
     * Delete an alert after verifying ownership.
     *
     * @throws AuthorizationException
     */
    public function destroyAreaAlert(Organization $organization, Area $area, Alert $alert): void
    {
        $this->assertAlertBelongsToAreaAndOrganization($organization, $area, $alert);
        $alert->delete();
    }
}
