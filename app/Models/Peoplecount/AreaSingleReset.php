<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use App\Models\User;
use Database\Factories\Peoplecount\AreaSingleResetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property int $reset_value
 * @property Carbon $effective_at
 * @property int $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'area_id',
    'reset_value',
    'effective_at',
    'created_by',
    'notes',
])]
#[Table(name: 'peoplecount_area_single_resets')]
class AreaSingleReset extends Model
{
    /** @use HasFactory<AreaSingleResetFactory> */
    use HasFactory;

    /**
     * The Area that owns the reset.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * The User who created the reset.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
        ];
    }
}
