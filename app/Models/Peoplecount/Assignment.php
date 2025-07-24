<?php

namespace App\Models\Peoplecount;

use App\Enums\Peoplecount\Direction;
use Database\Factories\Peoplecount\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $area_id
 * @property int $sensor_id
 * @property Direction $direction
 * @property Carbon $active_from
 * @property Carbon $active_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** The table associated with the model. */
    protected $table = 'peoplecount_assignments';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'area_id',
        'sensor_id',
        'direction',
        'active_from',
        'active_to',
    ];

    /**
     * The Event that owns the assignment.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The Area that owns the assignment.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * The Sensor that owns the assignment.
     *
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
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
            'direction' => Direction::class,
            'active_from' => 'datetime',
            'active_to' => 'datetime',
        ];
    }
}
