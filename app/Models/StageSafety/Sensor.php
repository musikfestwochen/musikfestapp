<?php

declare(strict_types=1);

namespace App\Models\StageSafety;

use App\Models\Organization;
use Database\Factories\StageSafety\SensorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $manufacturer
 * @property string $model
 * @property string $identifier
 * @property string|null $name
 * @property string|null $location
 * @property int $stale_after_seconds
 * @property Carbon|null $archived_at
 */
#[Fillable([
    'organization_id',
    'manufacturer',
    'model',
    'identifier',
    'name',
    'location',
    'stale_after_seconds',
    'archived_at',
])]
#[Table(name: 'stage_safety_sensors')]
class Sensor extends Model
{
    use HasApiTokens;

    /** @use HasFactory<SensorFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stale_after_seconds' => 300,
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stale_after_seconds' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
