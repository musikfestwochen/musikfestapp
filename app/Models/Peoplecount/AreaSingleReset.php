<?php

namespace App\Models\Peoplecount;

use App\Models\User;
use Database\Factories\Peoplecount\AreaSingleResetFactory;
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
class AreaSingleReset extends Model
{
    /** @use HasFactory<AreaSingleResetFactory> */
    use HasFactory;

    /** The table associated with the model. */
    protected $table = 'peoplecount_area_single_resets';

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'area_id',
        'reset_value',
        'effective_at',
        'created_by',
        'notes',
    ];

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
     * @pest-mutate-ignore
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
