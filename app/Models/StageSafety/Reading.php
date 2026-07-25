<?php

declare(strict_types=1);

namespace App\Models\StageSafety;

use App\Enums\StageSafety\ReadingKind;
use Carbon\CarbonImmutable;
use Database\Factories\StageSafety\ReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sensor_id
 * @property ReadingKind $kind
 * @property float $value
 * @property string $unit
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable $received_at
 * @property int|null $window_seconds
 * @property bool|null $battery_low
 * @property int|null $rssi_dbm
 * @property int|null $cv
 */
#[Fillable([
    'sensor_id',
    'kind',
    'value',
    'unit',
    'observed_at',
    'received_at',
    'window_seconds',
    'battery_low',
    'rssi_dbm',
    'cv',
])]
#[Table(name: 'stage_safety_readings')]
#[WithoutTimestamps]
class Reading extends Model
{
    /** @use HasFactory<ReadingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReadingKind::class,
            'value' => 'float',
            'observed_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'window_seconds' => 'integer',
            'battery_low' => 'boolean',
            'rssi_dbm' => 'integer',
            'cv' => 'integer',
        ];
    }
}
