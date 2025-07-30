<?php

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\AreaRecurringResetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RRule\RRule;

/**
 * @property int $id
 * @property int $area_id
 * @property int $reset_value
 * @property string $rrule
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
        'rrule',
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
     * Validate the RRULE format.
     */
    public function validateRRule(): bool
    {
        try {
            new RRule($this->rrule);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the next occurrences of this recurring reset.
     *
     * @return array<int, \DateTime>
     */
    public function getNextOccurrences(int $limit = 5): array
    {
        $rrule = $this->parseRRule();
        $occurrences = [];

        foreach ($rrule as $occurrence) {
            if (count($occurrences) >= $limit) {
                break;
            }

            $occurrences[] = $occurrence;
        }

        return $occurrences;
    }

    /**
     * Parse the RRULE and return an RRule instance.
     *
     * @return RRule<\DateTime>
     */
    public function parseRRule(): RRule
    {
        return new RRule($this->rrule);
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
        ];
    }
}
