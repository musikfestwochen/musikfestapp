<?php

namespace App\Models\Peoplecount;

use App\Models\Organization;
use Database\Factories\Peoplecount\SensorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Sensor extends Model
{
    protected $table = 'peoplecount_sensors';

    use HasApiTokens;

    /** @use HasFactory<SensorFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vendor',
        'model',
        'serial',
        'organization_id',
        'api_token', // TODO: Storing token in plaintext, revisit if API becomes sensitive
    ];

    /**
     * The Organization that owns the sensor.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The IntervalCounts associated with the sensor.
     *
     * @return HasMany<IntervalCount, $this>
     */
    public function intervalCounts(): HasMany
    {
        return $this->hasMany(IntervalCount::class);
    }

    /**
     * The Assignments associated with the sensor.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
