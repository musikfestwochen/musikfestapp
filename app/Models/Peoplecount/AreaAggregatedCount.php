<?php

namespace App\Models\Peoplecount;

use App\Casts\BinaryHexCast;
use Database\Factories\Peoplecount\AreaAggregatedCountFactory;
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
class AreaAggregatedCount extends Model
{
    /** @use HasFactory<AreaAggregatedCountFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'peoplecount_area_aggregated_counts';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'area_id',
        'count',
        'period_start',
        'period_end',
        'checksum',
    ];

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
