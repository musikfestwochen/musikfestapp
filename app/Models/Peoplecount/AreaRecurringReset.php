<?php

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\AreaRecurringResetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

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
     * @return array<Carbon>
     */
    public function getOccurrencesBetween(Carbon $start, Carbon $end): array
    {
        $occurrences = [];

        // Determine the first occurrence at or after the start boundary (inclusive)
        $current = $this->getPreviousDailyOccurrence($start);
        if ($current->lt($start)) {
            $current = $this->getNextDailyOccurrence($start);
        }

        // Collect occurrences inclusively up to the end boundary
        while ($current->lte($end)) {
            $occurrences[] = $current;
            // Advance to the next daily occurrence based on the current one
            $current = $this->getNextDailyOccurrence($current);
        }

        return $occurrences;
    }

    /**
     * Get the previous daily occurrence at reset_time in specified timezone.
     * Handles DST transitions by applying reset at defined time in current day.
     */
    public function getPreviousDailyOccurrence(?Carbon $from = null): Carbon
    {
        $appTimezone = (string) config('app.timezone');

        // Ensure we're working with a copy of the input date to avoid modifying the original
        $now = $from instanceof Carbon ? $from->copy()->setTimezone($this->timezone) : Date::now($this->timezone);
        $resetTime = Date::parse($this->reset_time, $this->timezone);

        // Set the date part of resetTime to match the date part of now
        $resetTime->setDate($now->year, $now->month, $now->day);

        // If today's reset time has not yet occurred, get yesterday's reset
        if ($now->lt($resetTime)) {
            $resetTime->subDay();
        }

        // Convert back to UTC for consistent database storage
        return $resetTime->setTimezone($appTimezone);
    }

    /**
     * Get the next daily occurrence at reset_time in specified timezone.
     * Handles DST transitions by applying reset at defined time in current day.
     */
    public function getNextDailyOccurrence(?Carbon $from = null): Carbon
    {
        $appTimezone = (string) config('app.timezone');

        // Ensure we're working with a copy of the input date to avoid modifying the original
        $now = $from instanceof Carbon ? $from->copy()->setTimezone($this->timezone) : Date::now($this->timezone);
        $resetTime = Date::parse($this->reset_time, $this->timezone);

        // Set the date part of resetTime to match the date part of now
        $resetTime->setDate($now->year, $now->month, $now->day);

        // If today's reset time has already passed, get tomorrow's reset
        if ($now->gte($resetTime)) {
            $resetTime->addDay();
        }

        // Convert back to UTC for consistent database storage
        return $resetTime->setTimezone($appTimezone);
    }

    /**
     * Get the attributes that should be cast.
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
