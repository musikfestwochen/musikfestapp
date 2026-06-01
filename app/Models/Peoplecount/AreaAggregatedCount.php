<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use App\Casts\BinaryHexCast;
use Database\Factories\Peoplecount\AreaAggregatedCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property int $count
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $checksum
 */
#[Fillable([
    'area_id',
    'count',
    'period_start',
    'period_end',
    'checksum',
])]
#[Table(name: 'peoplecount_area_aggregated_counts')]
#[WithoutTimestamps]
class AreaAggregatedCount extends Model
{
    /** @use HasFactory<AreaAggregatedCountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'checksum' => BinaryHexCast::class,
        ];
    }
}
