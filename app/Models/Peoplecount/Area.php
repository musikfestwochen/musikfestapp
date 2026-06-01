<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $event_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'event_id',
])]
#[Table(name: 'peoplecount_areas')]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The Event that owns the area.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Alerts that belong to the area.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * The Assignments that belong to the area.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The Single Resets that belong to the area (for route model binding).
     *
     * @return HasMany<AreaSingleReset, $this>
     */
    public function singleResets(): HasMany
    {
        return $this->areaSingleResets();
    }

    /**
     * The Single Resets that belong to the area.
     *
     * @return HasMany<AreaSingleReset, $this>
     */
    public function areaSingleResets(): HasMany
    {
        return $this->hasMany(AreaSingleReset::class);
    }

    /**
     * The Recurring Resets that belong to the area (for route model binding).
     *
     * @return HasMany<AreaRecurringReset, $this>
     */
    public function recurringResets(): HasMany
    {
        return $this->areaRecurringResets();
    }

    /**
     * The Recurring Resets that belong to the area.
     *
     * @return HasMany<AreaRecurringReset, $this>
     */
    public function areaRecurringResets(): HasMany
    {
        return $this->hasMany(AreaRecurringReset::class);
    }

    /**
     * The Aggregated Counts that belong to the area.
     *
     * @return HasMany<AreaAggregatedCount, $this>
     */
    public function aggregatedCounts(): HasMany
    {
        return $this->hasMany(AreaAggregatedCount::class);
    }
}
