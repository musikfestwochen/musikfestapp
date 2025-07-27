<?php

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\AreaFactory;
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
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    use SoftDeletes;

    /** The table associated with the model. */
    protected $table = 'peoplecount_areas';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'event_id',
    ];

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
     * The Assignments that belong to the area.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
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
     * The Single Resets that belong to the area (for route model binding).
     *
     * @return HasMany<AreaSingleReset, $this>
     */
    public function singleResets(): HasMany
    {
        return $this->hasMany(AreaSingleReset::class);
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
}
