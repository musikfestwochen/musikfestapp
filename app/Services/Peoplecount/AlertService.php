<?php

declare(strict_types=1);

namespace App\Services\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Notifications\Peoplecount\AreaOccupancyAlert;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class AlertService
{
    /** @var array|string[] */
    private const array ALERT_RELATIONS = [
        'creator',
        'recipients',
    ];

    /**
     * Process all alerts configured for the given area.
     */
    public function processAlertsForArea(Area $area): void
    {
        $area->loadMissing(['event', 'aggregatedCounts' => fn (HasMany $q) => $q->orderBy('period_end', 'desc'), 'alerts.recipients']);
        foreach ($area->alerts as $alert) {
            $this->processSingleAlert($alert, $area);
        }
    }

    /**
     * Evaluate and dispatch a single alert for the given area if conditions are met.
     */
    protected function processSingleAlert(Alert $alert, Area $area): void
    {
        $event = $area->event;

        $now = now();
        // 1. Event ongoing check already enforced by AreaService, but keep here for safety
        if (! ($event->starts_at <= $now && $now < $event->ends_at)) {
            return;
        }

        // 2. Get latest aggregated count
        $latest = $area->aggregatedCounts()->orderBy('period_end', 'desc')->first();
        if ($latest === null) {
            return; // nothing to evaluate
        }

        // 3. Cooldown check
        $lastTriggeredAt = $alert->last_triggered_at ? Date::parse($alert->last_triggered_at) : null;
        if ($lastTriggeredAt instanceof Carbon) {
            $cooldownUntil = $lastTriggeredAt->copy()->addMinutes($alert->cooldown_minutes);
            if ($now->lt($cooldownUntil)) {
                return; // still in cooldown
            }
        }

        // 3.2 Switch over event types (only occupancy for now)
        switch ($alert->type->value) {
            case AlertType::OccupancyAlert->value:
                $this->evaluateOccupancyAlert($alert, $area, $latest->count, $now);
                break;
            default:
                // No-op for unknown types for forward compatibility
                break;
        }
    }

    /**
     * Evaluate occupancy alert conditions and dispatch if all rules satisfied.
     */
    protected function evaluateOccupancyAlert(Alert $alert, Area $area, int $currentCount, Carbon $now): void
    {
        $threshold = $alert->occupancy_alert_threshold ?? null;
        if ($threshold === null) {
            return; // misconfigured
        }

        // 3.3 Condition: current count >= threshold
        if ($currentCount < $threshold) {
            return;
        }

        // 3.4 Ensure that since the last trigger, the count was below threshold at least once
        $mustHaveDroppedBelow = true;
        if ($alert->last_triggered_at === null) {
            $mustHaveDroppedBelow = false; // first trigger allowed immediately when threshold met
        }

        if ($mustHaveDroppedBelow) {
            $droppedBelow = $area->aggregatedCounts()
                ->where('period_end', '>', $alert->last_triggered_at)
                ->where('count', '<', $threshold)
                ->exists();
            if (! $droppedBelow) {
                return; // still continuously above threshold since last trigger
            }
        }

        // All conditions satisfied => dispatch
        $this->dispatchOccupancyNotification($alert, $area, $currentCount, $threshold);

        // Update last_triggered_at
        $alert->last_triggered_at = $now;
        $alert->save();
    }

    protected function dispatchOccupancyNotification(Alert $alert, Area $area, int $current, int $threshold): void
    {
        // Map configured channel to Laravel notification channels
        $channels = match ($alert->channel) {
            AlertChannel::Email => ['mail'],
            AlertChannel::Vonage => ['vonage'],
        };

        $notification = new AreaOccupancyAlert(
            eventName: $area->event->name,
            areaName: $area->name,
            currentOccupancy: $current,
            configuredThreshold: $threshold,
            channels: $channels,
        );

        // Send to all recipients
        foreach ($alert->recipients as $user) {
            $user->notify($notification);
        }
    }

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
            ->withCount('recipients')
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
        throw_if($event === null || $event->organization_id !== $organization->id, AuthorizationException::class, 'Area does not belong to the current organization.');
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
        throw_unless($invalid === [], AuthorizationException::class, 'One or more recipients do not belong to the current organization.');

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
        throw_if($alert->area_id !== $area->id, AuthorizationException::class, 'Alert does not belong to the specified area.');

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
