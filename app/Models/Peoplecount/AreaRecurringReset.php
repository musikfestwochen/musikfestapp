<?php

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\AreaRecurringResetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property int $reset_value
 * @property string $reset_time
 * @property string $timezone
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AreaRecurringReset extends Model
{
    /** @use HasFactory<AreaRecurringResetFactory> */
    use HasFactory;

    /** The table associated with the model. */
    protected $table = 'peoplecount_area_recurring_resets';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'area_id',
        'reset_value',
        'reset_time',
        'timezone',
        'notes',
    ];

    /**
     * The Area that owns the recurring reset.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the next daily occurrence at reset_time in specified timezone.
     * Handles DST transitions by applying reset at defined time in current day.
     */
    public function getNextDailyOccurrence(): Carbon
    {
        $now = Carbon::now($this->timezone);
        $resetTime = Carbon::createFromFormat('H:i', $this->reset_time, $this->timezone);

        // If today's reset time has already passed, get tomorrow's reset
        if ($now->format('H:i') >= $this->reset_time) {
            $resetTime->addDay();
        }

        return $resetTime;
    }

    /**
     * Get the previous daily occurrence at reset_time in specified timezone.
     * Handles DST transitions by applying reset at defined time in current day.
     */
    public function getPreviousDailyOccurrence(): Carbon
    {
        $now = Carbon::now($this->timezone);
        $resetTime = Carbon::createFromFormat('H:i', $this->reset_time, $this->timezone);

        // If today's reset time has not yet occurred, get yesterday's reset
        if ($now->format('H:i') < $this->reset_time) {
            $resetTime->subDay();
        }

        return $resetTime;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @pest-mutate-ignore
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reset_value' => 'integer',
            'reset_time' => 'string',
        ];
    }
}
