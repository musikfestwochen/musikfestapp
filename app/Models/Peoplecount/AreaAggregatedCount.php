<?php

namespace App\Models\Peoplecount;

use App\Casts\BinaryHexCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaAggregatedCount extends Model
{
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
        'from',
        'to',
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
            'from' => 'datetime',
            'to' => 'datetime',
            'checksum' => BinaryHexCast::class,
        ];
    }
}
