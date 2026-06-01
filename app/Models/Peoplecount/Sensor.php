<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use App\Models\Organization;
use Database\Factories\Peoplecount\SensorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $vendor
 * @property string $model
 * @property string $serial
 * @property int $organization_id
 * @property string|null $api_token
 */
#[Fillable([
    'vendor',
    'model',
    'serial',
    'organization_id',
    'api_token', // TODO: Storing token in plaintext, revisit if API becomes sensitive
])]
#[Table(name: 'peoplecount_sensors')]
class Sensor extends Model
{
    use HasApiTokens;

    /** @use HasFactory<SensorFactory> */
    use HasFactory;

    use SoftDeletes;

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
