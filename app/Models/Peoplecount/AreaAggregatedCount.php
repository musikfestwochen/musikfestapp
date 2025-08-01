<?php

namespace App\Models\Peoplecount;

use App\Casts\BinaryHexCast;
use Illuminate\Database\Eloquent\Model;

class AreaAggregatedCount extends Model
{
    protected $table = 'peoplecount_area_aggregated_counts';

    protected $fillable = [
        'area_id',
        'count',
        'from',
        'to',
        'checksum',
    ];

    protected $casts = [
        'from' => 'datetime',
        'to' => 'datetime',
        'checksum' => BinaryHexCast::class,
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
