<?php

namespace App\Models\Peoplecount;

use App\Models\Organization;
use Database\Factories\Peoplecount\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $organization_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use SoftDeletes;

    /** The table associated with the model. */
    protected $table = 'peoplecount_events';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'organization_id',
        'starts_at',
        'ends_at',
    ];

    /**
     * The Organization that owns the event.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The attributes that should be cast.
     *
     * Note: All datetime fields are stored in UTC timezone.
     * Frontend is responsible for displaying dates in the user's local timezone.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The Areas that belong to the event.
     *
     * @return HasMany<Area, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * The Assignments that belong to the event.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
