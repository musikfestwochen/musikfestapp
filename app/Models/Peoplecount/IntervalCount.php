<?php

namespace App\Models\Peoplecount;

use Database\Factories\Peoplecount\IntervalCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervalCount extends Model
{
    /** @use HasFactory<IntervalCountFactory> */
    use HasFactory;

    /**
     * Disable created_at and modified_at timestamps
     */
    public $timestamps = false;

    /** The table associated with the model. */
    protected $table = 'peoplecount_interval_counts';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sensor_id',
        'ts_from',
        'ts_to',
        'received_at',
        'count_in',
        'count_out',
    ];

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
