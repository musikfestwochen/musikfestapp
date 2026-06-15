<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use Carbon\CarbonImmutable;
use Database\Factories\Peoplecount\IntervalCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sensor_id
 * @property CarbonImmutable $ts_from
 * @property CarbonImmutable $ts_to
 * @property CarbonImmutable $received_at
 * @property int $count_in
 * @property int $count_out
 */
#[Fillable([
    'sensor_id',
    'ts_from',
    'ts_to',
    'received_at',
    'count_in',
    'count_out',
])]
#[Table(name: 'peoplecount_interval_counts')]
#[WithoutTimestamps]
class IntervalCount extends Model
{
    /** @use HasFactory<IntervalCountFactory> */
    use HasFactory;

    /**
     * Relationship with the Sensor model.
     *
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * The attributes that should be cast to native types.
     *
     * Note: All datetime fields are stored in UTC timezone.
     * Frontend is responsible for displaying dates in the user's local timezone.
     */
    protected function casts(): array
    {
        return [
            'ts_from' => 'immutable_datetime',
            'ts_to' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'count_in' => 'integer',
            'count_out' => 'integer',
        ];
    }
}
